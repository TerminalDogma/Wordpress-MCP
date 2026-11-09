<?php
namespace MSC;

/** Editorial reviews are local posts; linked catalog identities remain portable UUIDs. */
final class Reviews {
    public const ITEMS = array( 'msc_movie', 'msc_show', 'msc_episode', 'msc_game' );
    private static array $cache = array();
    public static function enabled( ?int $site = null ): bool { return (bool) get_blog_option( $site ?? get_current_blog_id(), 'msc_reviews_enabled', true ); }
    public static function score( $value ): ?float {
        return is_scalar( $value ) && is_numeric( $value ) && is_finite( (float) $value ) && $value >= 1 && $value <= 10 && abs( (float) $value * 2 - round( (float) $value * 2 ) ) < 0.00001 ? (float) $value : null;
    }
    public static function number( float $score ): string { return rtrim( rtrim( number_format( $score, 1, '.', '' ), '0' ), '.' ); }
    public static function award( float $score ): string { return $score >= 9.5 ? 'gold' : ( 9.0 === $score ? 'silver' : ( 8.5 === $score ? 'copper' : '' ) ); }
    public static function item_uuid( $value ): string {
        $r = is_numeric( $value ) ? Plugin::store()->by_post( (int) $value ) : ( is_string( $value ) ? Plugin::store()->get( $value ) : null );
        if ( ! $r || 'active' !== $r['state'] || ! in_array( $r['type'], self::ITEMS, true ) || ! Schema::enabled( $r['type'] ) ) { return ''; }
        $resolved = Plugin::store()->resolved( $r['uuid'] ); return $resolved && GamePolicy::accepts( $resolved ) ? $r['uuid'] : '';
    }
    public static function item( int $post ): ?array { $uuid = get_post_meta( $post, '_msc_review_item', true ); return is_string( $uuid ) && $uuid ? Presentation::record( $uuid ) : null; }
    public static function clear(): void { self::$cache = array(); }
    public static function own( array $r ): ?array {
        if ( ! self::enabled() || ! in_array( $r['type'], self::ITEMS, true ) || ! Schema::enabled( $r['type'] ) || ! GamePolicy::accepts( $r ) ) { return null; }
        $key = get_current_blog_id() . ':' . $r['uuid']; if ( array_key_exists( $key, self::$cache ) ) { return self::$cache[ $key ]; }
        $posts = get_posts( array( 'post_type' => 'msc_review', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => -1, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'meta_key' => '_msc_review_item', 'meta_value' => $r['uuid'] ) );
        foreach ( $posts as $post ) {
            $score = self::score( get_post_meta( $post->ID, '_msc_review_score', true ) ); if ( null === $score ) { continue; }
            return self::$cache[ $key ] = array( 'provider' => 'editorial', 'name' => Presentation::identity()['name'] ?? get_bloginfo( 'name' ), 'score' => $score, 'max' => 10, 'url' => get_permalink( $post ), 'review_id' => $post->ID, 'award' => self::award( $score ) );
        }
        return self::$cache[ $key ] = null;
    }
    public static function boot(): void {
        add_action( 'init', static function () {
            register_post_type( 'msc_review', array( 'labels' => array( 'name' => 'Reviews', 'singular_name' => 'Review', 'add_new_item' => 'Add Review', 'edit_item' => 'Edit Review' ), 'public' => self::enabled(), 'show_ui' => self::enabled(), 'show_in_rest' => self::enabled(), 'has_archive' => self::enabled() ? 'reviews' : false, 'rewrite' => array( 'slug' => 'reviews', 'with_front' => false ), 'menu_icon' => 'dashicons-star-half', 'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ) ) );
            foreach ( array( '_msc_review_item', '_msc_review_score', '_msc_review_verdict', '_msc_review_pros', '_msc_review_cons', '_msc_review_platform' ) as $key ) {
                register_post_meta( 'msc_review', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => static fn( $allowed, $meta, $post ) => current_user_can( 'edit_post', (int) $post ), 'sanitize_callback' => static function ( $v ) use ( $key ) { if ( '_msc_review_score' === $key ) { $s = self::score( $v ); return null === $s ? '' : self::number( $s ); } if ( '_msc_review_item' === $key ) { return self::item_uuid( $v ); } return sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ); } ) );
            }
            if ( ! get_option( 'msc_reviews_installed' ) ) { update_option( 'msc_reviews_installed', 1 ); update_option( 'msc_flush_rewrite', 1 ); }
        }, 7 );
        add_action( 'acf/init', static function () {
            $fields = array(
                array( 'key' => 'field_msc_review_item', 'name' => '_msc_review_item', 'label' => 'Reviewed item', 'type' => 'post_object', 'post_type' => array_values( array_filter( self::ITEMS, static fn( $t ) => Schema::enabled( $t ) ) ), 'return_format' => 'id', 'required' => 1, 'allow_null' => 0, 'instructions' => 'Choose the Movie, TV Show, Episode or Video Game reviewed on this site.' ),
                array( 'key' => 'field_msc_review_score', 'name' => '_msc_review_score', 'label' => 'Review score / 10', 'type' => 'number', 'min' => 1, 'max' => 10, 'step' => 0.5, 'required' => 1, 'instructions' => 'Half-point scores. 9.5–10: Gold; 9: Silver; 8.5: Copper.' ),
            );
            foreach ( array( 'verdict' => 'Final verdict', 'pros' => 'Positives (one per line)', 'cons' => 'Negatives (one per line)', 'platform' => 'Reviewed platform / edition (optional)' ) as $key => $label ) { $fields[] = array( 'key' => 'field_msc_review_' . $key, 'name' => '_msc_review_' . $key, 'label' => $label, 'type' => 'platform' === $key ? 'text' : 'textarea', 'rows' => 4, 'new_lines' => '' ); }
            acf_add_local_field_group( array( 'key' => 'group_msc_editorial_review', 'title' => 'Review verdict and score', 'fields' => $fields, 'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'msc_review' ) ) ) ) );
        } );
        add_filter( 'acf/load_value/key=field_msc_review_item', static function ( $v ) { return $v && is_string( $v ) && ! is_numeric( $v ) ? ( msc_local_post_id( $v ) ?? '' ) : $v; } );
        add_filter( 'acf/update_value/key=field_msc_review_item', static fn( $v ) => self::item_uuid( $v ) );
        add_filter( 'acf/validate_value/key=field_msc_review_item', static fn( $valid, $v ) => true !== $valid ? $valid : ( self::item_uuid( $v ) ? true : 'Choose an available catalog item enabled on this site.' ), 10, 2 );
        add_filter( 'acf/validate_value/key=field_msc_review_score', static fn( $valid, $v ) => true !== $valid ? $valid : ( null !== self::score( $v ) ? true : 'Enter a score from 1 to 10 in half-point increments.' ), 10, 2 );
        foreach ( array( 'save_post_msc_review', 'deleted_post_meta', 'added_post_meta', 'updated_post_meta', 'transition_post_status', 'deleted_post' ) as $hook ) { add_action( $hook, array( self::class, 'clear' ) ); }
        add_action( 'template_redirect', static function () { if ( is_singular( 'msc_review' ) && ( ! self::enabled() || ! self::item( get_queried_object_id() ) ) ) { global $wp_query; $wp_query->set_404(); status_header( 404 ); nocache_headers(); } } );
        add_filter( 'manage_msc_review_posts_columns', static function ( $columns ) { $columns['review_item'] = 'Reviewed item'; $columns['review_score'] = 'Score'; return $columns; } );
        add_action( 'manage_msc_review_posts_custom_column', static function ( $column, $id ) { if ( 'review_score' === $column ) { $s = self::score( get_post_meta( $id, '_msc_review_score', true ) ); echo null === $s ? '—' : esc_html( self::number( $s ) . '/10' ); } if ( 'review_item' === $column ) { $r = Plugin::store()->get( get_post_meta( $id, '_msc_review_item', true ) ); echo esc_html( $r['data']['title'] ?? '—' ); } }, 10, 2 );
    }
}
