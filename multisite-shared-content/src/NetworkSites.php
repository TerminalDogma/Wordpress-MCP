<?php
namespace MSC;

/** Four presentation identities and an editable network content-type matrix. */
final class NetworkSites {
    public static function profiles(): array {
        $profiles = array(
            'a' => array( 'name' => 'ComingSoon.net', 'label' => 'Site A · ComingSoon', 'source' => 'https://www.comingsoon.net/', 'color' => '#187DAF', 'ink' => '#ffffff', 'header' => '#122E39', 'highlight' => '#DFE000', 'font' => 'Archivo', 'body_font' => 'Open Sans', 'tagline' => 'Movie, TV and streaming news', 'types' => array( 'msc_movie', 'msc_show', 'msc_episode', 'msc_person', 'msc_franchise' ), 'policy' => 'all', 'sections' => array( 'Top Stories', 'Must Read', 'Breaking News' ), 'nav' => array( 'Streaming', 'Movies', 'TV', 'Games', 'Trailers', 'Genres' ) ),
            'b' => array( 'name' => 'SuperHeroHype', 'label' => 'Site B · SuperHeroHype', 'source' => 'https://www.superherohype.com/', 'color' => '#C30912', 'ink' => '#ffffff', 'header' => '#87060C', 'highlight' => '#F6C223', 'font' => 'Roboto', 'body_font' => 'Rubik', 'tagline' => 'Superhero movie and TV news', 'types' => array( 'msc_movie', 'msc_show', 'msc_episode', 'msc_franchise', 'msc_comic', 'msc_comic_issue', 'msc_comic_arc' ), 'policy' => 'all', 'sections' => array( 'Top Stories', 'Breaking News', 'Latest News' ), 'nav' => array( 'Movies', 'TV', 'Comics', 'News', 'Geek Culture', 'Features' ) ),
            'c' => array( 'name' => 'GameRevolution', 'label' => 'Site C · GameRevolution', 'source' => 'https://www.gamerevolution.com/', 'color' => '#DF1362', 'ink' => '#ffffff', 'header' => '#ffffff', 'highlight' => '#DF1362', 'font' => 'Play', 'body_font' => 'Rubik', 'tagline' => 'Video game news, reviews and guides', 'types' => array( 'msc_game' ), 'policy' => 'all', 'sections' => array( 'Featured Reviews', 'Gaming Culture', 'Gaming News' ), 'nav' => array( 'Home', 'News', 'Reviews', 'Guides', 'Games', 'Videos', 'Best Products' ) ),
            'd' => array( 'name' => 'PlayStation LifeStyle', 'label' => 'Site D · PlayStation LifeStyle', 'source' => 'https://www.playstationlifestyle.net/', 'color' => '#0072BB', 'ink' => '#ffffff', 'header' => '#030C1B', 'highlight' => '#2BA1CB', 'font' => 'Inter', 'body_font' => 'Inter', 'tagline' => 'PlayStation news, reviews and features', 'types' => array( 'msc_game' ), 'policy' => 'sony_all', 'sections' => array( 'Featured Stories', 'More Stories', 'Trending' ), 'nav' => array( 'News', 'PS5', 'PS4', 'PSVR', 'Features', 'Reviews' ) ),
        );
        foreach ( $profiles as &$profile ) { $profile['types'] = array_merge( $profile['types'], EntityCatalog::types_for( $profile['types'] ) ); } unset( $profile ); return $profiles;
    }
    public static function key( string $path ): string { return array( '/site-a/' => 'a', '/site-b/' => 'b', '/site-c/' => 'c', '/site-d/' => 'd' )[ $path ] ?? ''; }
    public static function identity(): array { $key = get_option( 'msc_site_identity', 'a' ); return self::profiles()[ $key ] ?? self::profiles()['a']; }
    public static function setup(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Network administrator permission required.' ); }
        \WP_Theme::network_enable_theme( 'msc-screening-room' );
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0 ) ) as $site ) {
            $key = self::key( $site->path ); if ( ! $key ) { continue; } $profile = self::profiles()[ $key ]; $id = (int) $site->blog_id;
            if ( (int) get_blog_option( $id, 'msc_network_profile_version', 0 ) < 200 ) {
                Schema::participate( $id, array_values( array_intersect( $profile['types'], array_keys( Schema::all() ) ) ) );
                update_blog_option( $id, 'msc_game_platform_policy', $profile['policy'] ); update_blog_option( $id, 'msc_episodes_as_children', true );
                update_blog_option( $id, 'msc_site_identity', $key ); update_blog_option( $id, 'blogname', $profile['name'] ); update_blog_option( $id, 'blogdescription', $profile['tagline'] );
                update_blog_option( $id, 'show_on_front', 'posts' ); update_blog_option( $id, 'timezone_string', 'America/New_York' ); update_blog_option( $id, 'msc_network_profile_version', 200 );
                switch_to_blog( $id ); try { switch_theme( 'msc-screening-room' ); } finally { restore_current_blog(); }
            }
            switch_to_blog( $id ); try { self::calendar_page(); UpcomingReleases::hub(); ReleasePages::upgrade(); update_option( 'msc_flush_rewrite', 1 ); } finally { restore_current_blog(); }
        }
        EntityCatalog::enroll(); Comics::enroll(); Franchises::enroll(); StreamingServices::seed();
    }
    public static function calendar_page(): int {
        $saved = (int) get_option( 'msc_calendar_page', 0 ); $post = $saved ? get_post( $saved ) : null; if ( $post && 'trash' !== $post->post_status && 'page' === $post->post_type ) { return $saved; }
        $existing = get_page_by_path( 'release-calendar' );
        if ( $existing && str_contains( $existing->post_content, '[msc_release_calendar]' ) && 'trash' !== $existing->post_status ) { update_option( 'msc_calendar_page', $existing->ID ); return (int) $existing->ID; }
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Release Calendar', 'post_name' => 'release-calendar', 'post_content' => '[msc_release_calendar]', 'meta_input' => array( '_msc_release_calendar' => '1' ) ), true );
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( 'Could not create the release calendar page.' ); } update_option( 'msc_calendar_page', (int) $id ); return (int) $id;
    }
    public static function calendar_url(): string { $id = (int) get_option( 'msc_calendar_page', 0 ); return $id ? (string) get_permalink( $id ) : home_url( '/release-calendar/' ); }
    public static function boot(): void {
        add_action( 'network_admin_menu', static function () { add_submenu_page( 'msc-network', 'Sites and Content Types', 'Sites and Content Types', 'manage_network_options', 'msc-sites', array( self::class, 'page' ) ); }, 20 );
        add_action( 'admin_menu', static function () { if ( current_user_can( 'manage_network_options' ) ) { add_submenu_page( 'msc', 'Site Content Types', 'Site Content Types', 'manage_network_options', 'msc-site-types', static function () { echo '<div class="wrap"><h1>Site Content Types</h1><p>Manage every site from the network matrix.</p><a class="button button-primary" href="' . esc_url( network_admin_url( 'admin.php?page=msc-sites#msc-site-' . get_current_blog_id() ) ) . '">Open sites and content types</a></div>'; } ); } }, 20 );
        add_action( 'admin_post_msc_site_types', array( self::class, 'save' ) );
    }
    public static function page(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { wp_die( 'Network administrator permission required.' ); }
        echo '<div class="wrap msc-sites"><h1>Sites and Content Types</h1><p>Choose each site’s catalog types and Video Game platform policy. Turning a type off hides its local catalog; shared records and local overrides remain available when it is enabled again.</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success inline"><p>Site settings saved. Local projections are updating through the queue.</p></div>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_site_types">'; wp_nonce_field( 'msc_site_types' );
        echo '<div class="msc-site-matrix-wrap" role="region" aria-label="Site content type settings" tabindex="0"><table class="widefat striped msc-site-matrix"><thead><tr><th>Site</th>'; foreach ( Schema::all() as $type => $schema ) { echo '<th>' . esc_html( $schema['label'] ) . '</th>'; } echo '<th>Reviews</th><th>Game platforms</th><th>Episode navigation</th></tr></thead><tbody>';
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 100, 'offset' => isset( $_GET['sites_offset'] ) && is_scalar( $_GET['sites_offset'] ) ? absint( $_GET['sites_offset'] ) : 0 ) ) as $site ) {
            $id = (int) $site->blog_id; $label = get_blog_option( $id, 'blogname' ) . ' (' . $site->path . ')'; $enabled = get_blog_option( $id, 'msc_types', array() );
            echo '<tr id="msc-site-' . $id . '" data-site="' . $id . '"><th scope="row"><a href="' . esc_url( get_admin_url( $id ) ) . '">' . esc_html( $label ) . '</a><input type="hidden" name="sites[' . $id . '][present]" value="1"></th>';
            foreach ( Schema::all() as $type => $schema ) { echo '<td><label><input type="checkbox" name="sites[' . $id . '][types][]" value="' . esc_attr( $type ) . '" aria-label="' . esc_attr( $schema['label'] . ' on ' . $label ) . '"' . checked( in_array( $type, $enabled, true ), true, false ) . '><span class="screen-reader-text">' . esc_html( $schema['label'] ) . '</span></label></td>'; }
            echo '<td><label><input type="checkbox" name="sites[' . $id . '][reviews]" value="1" aria-label="Reviews on ' . esc_attr( $label ) . '"' . checked( Reviews::enabled( $id ), true, false ) . '> Reviews</label></td>';
            echo '<td><select name="sites[' . $id . '][policy]" aria-label="Game platform policy for ' . esc_attr( $label ) . '">'; foreach ( array( 'all' => 'All platforms', 'sony_all' => 'Sony consoles + handhelds', 'sony_home' => 'Sony home consoles only' ) as $key => $name ) { echo '<option value="' . $key . '"' . selected( GamePolicy::mode( $id ), $key, false ) . '>' . esc_html( $name ) . '</option>'; } echo '</select></td><td><label><input type="checkbox" name="sites[' . $id . '][children]" value="1"' . checked( (bool) get_blog_option( $id, 'msc_episodes_as_children', false ), true, false ) . '> Under TV Shows</label></td></tr>';
        }
        echo '</tbody></table></div>'; submit_button( 'Save site content types' ); echo '</form><p>Episodes can be enabled as TV Show child content independently of the top-level catalog menu. The Sony policy applies to game membership, console/platform pages, public platform labels, release dates and calendars.</p></div>';
    }
    public static function apply( array $sites ): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Network administrator permission required.' ); } $valid = array();
        foreach ( $sites as $id => $row ) {
            if ( ! is_array( $row ) ) { throw new \InvalidArgumentException( 'Invalid site settings. No settings were applied.' ); }
            $site = get_site( (int) $id ); $types = $row['types'] ?? array(); $policy = $row['policy'] ?? 'all';
            if ( ! $site || (int) $site->network_id !== get_current_network_id() || ! is_array( $types ) || ! array_is_list( $types ) || array_filter( $types, static fn( $t ) => ! is_string( $t ) ) || array_diff( $types, array_keys( Schema::all() ) ) || ! in_array( $policy, array( 'all', 'sony_all', 'sony_home' ), true ) ) { throw new \InvalidArgumentException( 'Invalid site settings. No settings were applied.' ); }
            $valid[ (int) $id ] = array( 'types' => array_values( array_unique( $types ) ), 'policy' => $policy, 'children' => ! empty( $row['children'] ), 'reviews' => isset( $row['present'] ) ? ! empty( $row['reviews'] ) : Reviews::enabled( (int) $id ) );
        }
        foreach ( $valid as $id => $row ) { if ( Reviews::enabled( $id ) !== $row['reviews'] ) { update_blog_option( $id, 'msc_reviews_enabled', (int) $row['reviews'] ); update_blog_option( $id, 'msc_flush_rewrite', 1 ); } update_blog_option( $id, 'msc_game_platform_policy', $row['policy'] ); update_blog_option( $id, 'msc_episodes_as_children', $row['children'] ); Schema::participate( $id, $row['types'] ); if ( in_array( 'msc_game', $row['types'], true ) ) { Queue::seed( $id, 'msc_game' ); } }
    }
    public static function save(): void {
        check_admin_referer( 'msc_site_types' ); try { $sites = $_POST['sites'] ?? array(); if ( ! is_array( $sites ) ) { throw new \InvalidArgumentException( 'Invalid site settings.' ); } self::apply( wp_unslash( $sites ) ); wp_safe_redirect( network_admin_url( 'admin.php?page=msc-sites&saved=1' ) ); exit; }
        catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Site Content Types', array( 'response' => current_user_can( 'manage_network_options' ) ? 400 : 403, 'back_link' => true ) ); }
    }
}
