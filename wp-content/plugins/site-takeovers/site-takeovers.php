<?php
/**
 * Plugin Name: Site Takeovers
 * Description: Site-wide and page-specific advertising skins with banner and side placements.
 * Version: 1.3.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * License: GPL-2.0-or-later
 * Text Domain: site-takeovers
 */

namespace SiteTakeovers;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes/schedule.php';

final class Plugin {
    public const TYPE = 'sat_campaign';
    public const META = '_sat_campaign';
    public const VERSION = '1.3.0';
    public const PLACEMENTS = array( 'banner' => 'Top banner', 'left' => 'Left side ad', 'right' => 'Right side ad' );
    public const SLOTS = self::PLACEMENTS + array( 'background' => 'Page background image', 'logo' => 'Campaign logo' );
    public const COLORS = array(
        'background' => array( 'Page background', '#0c1712' ),
        'surface' => array( 'Content background', '#f0f3e9' ),
        'text' => array( 'Content text', '#19291f' ),
        'accent' => array( 'Accent and links', '#365739' ),
        'header' => array( 'Header and campaign color', '#16291e' ),
    );
    private static ?array $active = null;
    private static bool $resolved = false;
    private static bool $private_preview = false;
    private static bool $time_sensitive = false;

    public static function boot(): void {
        add_action( 'init', array( self::class, 'register' ) );
        add_action( 'template_redirect', array( self::class, 'cache_headers' ), 0 );
        add_action( 'wp_enqueue_scripts', array( self::class, 'styles' ), 45 );
        add_filter( 'body_class', array( self::class, 'body_class' ) );
        add_action( 'msc_screening_room_after_header', array( self::class, 'render' ) );
        add_action( 'wp_body_open', static function (): void {
            if ( 'msc-screening-room' !== get_template() ) { self::render(); }
        }, 20 );
        if ( is_admin() ) {
            require_once __DIR__ . '/includes/admin.php';
            Admin::boot();
        }
    }

    public static function register(): void {
        register_taxonomy_for_object_type( 'post_tag', 'page' );
        $capabilities = array_fill_keys( array(
            'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts', 'create_posts',
            'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts',
            'publish_posts', 'read_private_posts',
        ), 'manage_options' );
        register_post_type( self::TYPE, array(
            'labels' => array(
                'name' => 'Ad Takeovers', 'singular_name' => 'Ad Takeover', 'add_new_item' => 'Add Ad Takeover',
                'edit_item' => 'Edit Ad Takeover', 'all_items' => 'Ad Takeovers',
            ),
            'public' => false, 'show_ui' => true, 'show_in_rest' => false,
            'menu_icon' => 'dashicons-layout', 'supports' => array( 'title' ),
            'capability_type' => 'post', 'map_meta_cap' => true, 'capabilities' => $capabilities,
        ) );
    }

    public static function url( $value ): string {
        $parts = is_string( $value ) ? wp_parse_url( $value ) : false;
        return $parts && in_array( strtolower( $parts['scheme'] ?? '' ), array( 'https', 'http' ), true ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? esc_url_raw( $value, array( 'https', 'http' ) ) : '';
    }

    public static function read( int $id ): array {
        $saved = get_post_meta( $id, self::META, true );
        return is_array( $saved ) ? $saved : array();
    }

    public static function public_types(): array {
        $types = array_filter( get_post_types( array(), 'objects' ), 'is_post_type_viewable' );
        unset( $types['attachment'] );
        return array_keys( $types );
    }

    private static function target_id( string $value ): int {
        if ( ctype_digit( $value ) ) { return (int) $value; }
        $url = self::url( $value );
        if ( ! $url ) { return 0; }
        $parts = wp_parse_url( $url );
        $home = wp_parse_url( home_url( '/' ) );
        $path = trailingslashit( $parts['path'] ?? '/' );
        if ( strcasecmp( $parts['host'], $home['host'] ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) || ! str_starts_with( $path, trailingslashit( $home['path'] ?? '/' ) ) ) { return 0; }
        if ( is_multisite() ) {
            $subsite = get_site_by_path( get_site()->domain, $path );
            if ( $subsite && (int) $subsite->blog_id !== get_current_blog_id() ) { return 0; }
        }
        return (int) url_to_postid( $url );
    }

    /** Settings are site-local. Only media-library images, text and ordinary links are accepted. */
    public static function save( int $id, array $input ): void {
        $scope = $input['scope'] ?? 'off';
        $settings = array(
            'scope' => in_array( $scope, array( 'off', 'site', 'pages', 'tags' ), true ) ? $scope : 'off',
            'targets' => array(), 'tags' => array(), 'colors' => array(), 'art' => array(), 'links' => array(),
            'window' => Schedule::from_input( (array) ( $input['window'] ?? array() ) ),
        );
        $targets = $input['targets'] ?? '';
        $manual = is_scalar( $targets ) ? preg_split( '/\R/u', (string) $targets ) : $targets;
        foreach ( array_merge( (array) ( $input['post_ids'] ?? array() ), (array) $manual ) as $value ) {
            if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) { continue; }
            $post_id = self::target_id( trim( (string) $value ) );
            $post = $post_id ? get_post( $post_id ) : null;
            if ( $post && is_post_type_viewable( $post->post_type ) ) { $settings['targets'][ $post_id ] = $post_id; }
        }
        $settings['targets'] = array_values( $settings['targets'] );
        foreach ( (array) ( $input['tags'] ?? array() ) as $value ) {
            if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || (int) $value < 1 ) { continue; }
            $tag = get_term( (int) $value, 'post_tag' );
            if ( $tag instanceof \WP_Term ) { $settings['tags'][ $tag->term_id ] = (int) $tag->term_id; }
        }
        $settings['tags'] = array_values( $settings['tags'] );
        foreach ( self::COLORS as $key => list( , $default ) ) {
            $color = $input['colors'][ $key ] ?? '';
            $settings['colors'][ $key ] = is_string( $color ) ? sanitize_hex_color( $color ) ?: $default : $default;
        }
        foreach ( self::SLOTS as $key => $label ) {
            $attachment = $input['art'][ $key ] ?? '';
            $attachment = is_scalar( $attachment ) ? absint( $attachment ) : 0;
            if ( $attachment && wp_attachment_is_image( $attachment ) ) { $settings['art'][ $key ] = $attachment; }
            if ( isset( self::PLACEMENTS[ $key ] ) ) { $settings['links'][ $key ] = self::url( $input['links'][ $key ] ?? '' ); }
        }
        $settings['destination'] = self::url( $input['destination'] ?? '' );
        foreach ( array( 'brand' => 60, 'headline' => 100, 'tagline' => 120, 'message' => 140, 'cta' => 36 ) as $key => $length ) {
            $text = $input[ $key ] ?? '';
            $settings[ $key ] = is_string( $text ) ? mb_substr( sanitize_text_field( $text ), 0, $length ) : '';
        }
        update_post_meta( $id, self::META, wp_slash( $settings ) );
    }

    private static function matches( array $data, int $target ): bool {
        return match ( $data['scope'] ?? 'off' ) {
            'site' => true,
            'pages' => $target && in_array( $target, $data['targets'] ?? array(), true ),
            'tags' => $target && ! empty( $data['tags'] ) && has_term( $data['tags'], 'post_tag', $target ),
            default => false,
        };
    }

    public static function preview_url( int $id ): string {
        $data = self::read( $id );
        $url = 'site' === ( $data['scope'] ?? '' ) ? home_url( '/' ) : '';
        foreach ( $data['targets'] ?? array() as $target ) {
            if ( $url ) { break; }
            $post = get_post( $target );
            if ( $post && 'publish' === $post->post_status && ! $post->post_password && self::matches( $data, $target ) ) { $url = get_permalink( $post ); }
        }
        if ( 'tags' === ( $data['scope'] ?? '' ) && ! empty( $data['tags'] ) ) {
            $types = array_intersect( self::public_types(), get_taxonomy( 'post_tag' )->object_type );
            $posts = $types ? get_posts( array(
                'post_type' => array_values( $types ), 'post_status' => 'publish', 'has_password' => false,
                'tag__in' => $data['tags'], 'numberposts' => 1, 'fields' => 'ids',
            ) ) : array();
            if ( $posts ) { $url = get_permalink( $posts[0] ); }
        }
        return $url ? add_query_arg( array( 'sat_preview' => $id, 'sat_preview_nonce' => wp_create_nonce( 'sat_preview_' . get_current_blog_id() . '_' . $id ) ), $url ) : '';
    }

    private static function preview( int $target ): ?array {
        $raw_id = $_GET['sat_preview'] ?? null;
        $nonce = $_GET['sat_preview_nonce'] ?? null;
        if ( ! is_string( $raw_id ) || ! ctype_digit( $raw_id ) || ! is_string( $nonce ) || ! current_user_can( 'manage_options' ) ) { return null; }
        $id = (int) $raw_id;
        $post = get_post( $id );
        if ( ! $id || ! $post || self::TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) || ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( wp_unslash( $nonce ), 'sat_preview_' . get_current_blog_id() . '_' . $id ) ) { return null; }
        $data = self::read( $id );
        return self::matches( $data, $target ) ? $data + array( 'title' => get_the_title( $post ) ) : null;
    }

    public static function cache_headers(): void {
        self::active();
        if ( self::$private_preview || self::$time_sensitive ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
            nocache_headers();
        }
        if ( self::$private_preview ) { add_filter( 'wp_robots', 'wp_robots_no_robots' ); }
    }

    public static function active(): ?array {
        if ( self::$resolved ) { return self::$active; }
        self::$resolved = true;
        if ( is_admin() || is_feed() || is_embed() || is_404() || is_preview() || ( is_singular() && post_password_required() ) ) { return null; }
        $target = is_singular() ? get_queried_object_id() : 0;
        if ( $preview = self::preview( $target ) ) {
            self::$private_preview = true;
            return self::$active = $preview;
        }
        $campaigns = get_posts( array(
            'post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => -1,
            'orderby' => array( 'modified' => 'DESC', 'ID' => 'DESC' ),
        ) );
        $live = array();
        $now = time();
        foreach ( $campaigns as $post ) {
            $data = self::read( (int) $post->ID );
            if ( ! self::matches( $data, $target ) ) { continue; }
            $window = $data['window'] ?? array();
            $state = Schedule::state( $window, $now );
            if ( 'Ended' !== $state && ( isset( $window['start'] ) || isset( $window['end'] ) ) ) { self::$time_sensitive = true; }
            if ( 'Running' !== $state ) { continue; }
            $data['title'] = get_the_title( $post );
            $live[ $data['scope'] ] ??= $data;
        }
        return self::$active = $live['pages'] ?? $live['tags'] ?? $live['site'] ?? null;
    }

    private static function has_rails( array $data ): bool {
        return ! empty( $data['art']['left'] ) || ! empty( $data['art']['right'] );
    }

    public static function body_class( array $classes ): array {
        $data = self::active();
        if ( ! $data ) { return $classes; }
        $classes[] = 'sat-active';
        if ( self::has_rails( $data ) ) { $classes[] = 'sat-has-rails'; }
        if ( ! empty( $data['art']['background'] ) ) { $classes[] = 'sat-has-wallpaper'; }
        return $classes;
    }

    public static function styles(): void {
        $data = self::active();
        if ( ! $data ) { return; }
        wp_enqueue_style( 'site-takeovers', plugins_url( 'assets/takeovers.css', __FILE__ ), array(), self::VERSION );
        if ( self::has_rails( $data ) ) {
            wp_enqueue_script( 'site-takeovers-rails', plugins_url( 'assets/rails.js', __FILE__ ), array(), self::VERSION, true );
        }
        $vars = '';
        foreach ( self::COLORS as $key => list( , $default ) ) {
            $color = sanitize_hex_color( $data['colors'][ $key ] ?? '' ) ?: $default;
            $vars .= '--sat-' . $key . ':' . $color . ';';
        }
        $background = ! empty( $data['art']['background'] ) ? wp_get_attachment_image_url( $data['art']['background'], 'full' ) : '';
        if ( $background ) {
            $vars .= '--sat-background-image:url(' . wp_json_encode( esc_url_raw( $background ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . ');';
        }
        wp_add_inline_style( 'site-takeovers', 'body.sat-active{' . $vars . '}' );
    }

    public static function render(): void {
        $data = self::active();
        if ( ! $data ) { return; }
        $slots = array_intersect_key( $data['art'], self::PLACEMENTS );
        if ( ! $slots ) { return; }
        $logo = empty( $data['art']['logo'] ) ? '' : wp_get_attachment_image( $data['art']['logo'], 'full', false, array(
            'class' => 'sat-logo', 'alt' => $data['headline'] ?: $data['title'], 'decoding' => 'async',
        ) );
        ?>
        <aside class="sat-stage" aria-label="Advertising">
            <?php foreach ( $slots as $slot => $attachment ) {
                $destination = ( $data['links'][ $slot ] ?? '' ) ?: ( $data['destination'] ?? '' );
                $overlay = $logo || '' !== $data['headline'];
                $image = wp_get_attachment_image( $attachment, 'banner' === $slot ? 'large' : 'medium_large', false, array(
                    'class' => 'sat-image', 'alt' => $overlay ? '' : ( get_post_meta( $attachment, '_wp_attachment_image_alt', true ) ?: $data['title'] ),
                    'loading' => 'eager', 'decoding' => 'async',
                ) );
                if ( ! $image ) { continue; }
                $is_rail = 'banner' !== $slot;
                ?>
                <?php if ( $is_rail ) { ?><div class="sat-rail-track sat-rail-track-<?php echo esc_attr( $slot ); ?>"><?php } ?>
                <div class="sat-slot sat-<?php echo esc_attr( $slot ); ?>">
                    <span class="sat-label">Advertisement</span>
                    <?php if ( $destination ) { ?><a class="sat-creative" href="<?php echo esc_url( $destination ); ?>" rel="sponsored"><?php } else { ?><div class="sat-creative"><?php } ?>
                        <?php echo $image; ?>
                        <?php if ( $overlay ) { ?>
                            <div class="sat-copy">
                                <?php if ( $logo ) { echo $logo; } else { ?>
                                    <?php if ( $data['brand'] ) { ?><p class="sat-brand"><?php echo esc_html( $data['brand'] ); ?></p><?php } ?>
                                    <p class="sat-headline"><?php echo esc_html( $data['headline'] ); ?></p>
                                <?php } ?>
                                <?php if ( ! empty( $data['tagline'] ) ) { ?><p class="sat-tagline"><?php echo esc_html( $data['tagline'] ); ?></p><?php } ?>
                                <?php if ( $data['message'] ) { ?><p class="sat-message"><?php echo esc_html( $data['message'] ); ?></p><?php } ?>
                                <?php if ( $data['cta'] && $destination ) { ?><span class="sat-cta"><?php echo esc_html( $data['cta'] ); ?> <span aria-hidden="true">↗</span></span><?php } ?>
                            </div>
                        <?php } ?>
                    <?php echo $destination ? '</a>' : '</div>'; ?>
                </div>
                <?php if ( $is_rail ) { ?></div><?php } ?>
            <?php } ?>
        </aside>
        <?php
    }
}

Plugin::boot();
