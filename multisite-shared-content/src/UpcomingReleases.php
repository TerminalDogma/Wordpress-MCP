<?php
namespace MSC;

/** Live monthly release guides built from the existing publication-aware date index. */
final class UpcomingReleases {
    public const SHORTCODE = 'msc_upcoming_releases';
    public static function types(): array { return array_filter( ReleaseCalendar::FIELDS, static fn( $type ) => Schema::enabled( $type ), ARRAY_FILTER_USE_KEY ); }
    public static function filters( array $args ): array {
        $f = array( 'month' => $args['month'] ?? wp_date( 'Y-m' ), 'type' => '', 'service' => '', 'search' => '', 'view' => 'cards', 'past' => '0', 'page' => 1 );
        ReleaseCalendar::month( $f['month'] );
        foreach ( array( 'type', 'service', 'search', 'view', 'past' ) as $key ) {
            $value = $args[ $key ] ?? $f[ $key ];
            if ( ! is_string( $value ) || mb_strlen( $value ) > 100 ) { throw new \InvalidArgumentException( 'Invalid release-page filter.' ); }
            $f[ $key ] = sanitize_text_field( $value );
        }
        if ( $f['type'] && ! isset( self::types()[ $f['type'] ] ) ) { throw new \InvalidArgumentException( 'This release type is not enabled on this site.' ); }
        if ( ! in_array( $f['view'], array( 'cards', 'list' ), true ) || ! in_array( $f['past'], array( '0', '1' ), true ) ) { throw new \InvalidArgumentException( 'Choose cards or list view.' ); }
        if ( $f['service'] && ! wp_is_uuid( $f['service'] ) ) { throw new \InvalidArgumentException( 'Choose a published streaming service.' ); }
        $page = $args['page'] ?? '1'; if ( ! is_scalar( $page ) || ! preg_match( '/^[1-9][0-9]{0,6}$/D', (string) $page ) ) { throw new \InvalidArgumentException( 'Invalid release page number.' ); } $f['page'] = (int) $page;
        return $f;
    }
    public static function url( array $args = array(), string $base = '' ): string {
        $base = $base ?: self::hub_url();
        $keys = array( 'month', 'type', 'service', 'search', 'view', 'past', 'page' ); $query = array();
        foreach ( $keys as $key ) { if ( isset( $args[ $key ] ) ) { $query[ 'release_' . $key ] = $args[ $key ]; } }
        return add_query_arg( $query, $base ) . '#upcoming-releases';
    }
    public static function hub_url(): string { $id = (int) get_option( 'msc_upcoming_page', 0 ); return $id ? (string) get_permalink( $id ) : home_url( '/upcoming-releases/' ); }
    public static function hub(): int {
        $saved = (int) get_option( 'msc_upcoming_page', 0 ); $post = $saved ? get_post( $saved ) : null;
        if ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) { return $saved; }
        $existing = get_page_by_path( 'upcoming-releases' );
        if ( $existing && has_shortcode( $existing->post_content, self::SHORTCODE ) && 'trash' !== $existing->post_status ) { update_option( 'msc_upcoming_page', $existing->ID ); return (int) $existing->ID; }
        $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Upcoming Releases', 'post_name' => 'upcoming-releases', 'post_content' => '[' . self::SHORTCODE . ']', 'meta_input' => array( '_msc_upcoming_releases' => '1' ) ), true );
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( 'Could not create the upcoming releases page.' ); }
        update_option( 'msc_upcoming_page', (int) $id ); return (int) $id;
    }
    public static function data( array $args ): array {
        $f = self::filters( $args ); $calendar = ReleaseCalendar::data( $f['month'], $f['type'] ); $directory = $calendar['services'];
        if ( $f['service'] && ! isset( $directory[ $f['service'] ] ) ) { throw new \InvalidArgumentException( 'This streaming service is not published on this site.' ); }
        $events = array(); $records = array(); $counts = array_fill_keys( array_keys( $directory ), 0 );
        foreach ( $calendar['events'] as $event ) {
            if ( '0' === $f['past'] && $f['month'] === wp_date( 'Y-m' ) && $event['date'] < wp_date( 'Y-m-d' ) ) { continue; }
            $uuid = $event['uuid']; if ( ! array_key_exists( $uuid, $records ) ) { $records[ $uuid ] = Presentation::record( $uuid ); } $r = $records[ $uuid ]; if ( ! $r ) { continue; }
            $parent = null;
            if ( 'msc_episode' === $r['type'] && ! empty( $r['data']['parent_show'] ) ) {
                $id = $r['data']['parent_show']; if ( ! array_key_exists( $id, $records ) ) { $records[ $id ] = Presentation::record( $id ); } $candidate = $records[ $id ]; if ( $candidate && 'msc_show' === $candidate['type'] ) { $parent = $candidate; }
            }
            $services = $event['services'];
            $event['services'] = $services; $event['parent_url'] = $parent ? Presentation::url( $parent ) : '';
            $event['label'] = $event['kind'];
            if ( 'msc_episode' === $r['type'] && isset( $r['data']['season_number'], $r['data']['episode_number'] ) ) { $event['label'] = 1 === (int) $r['data']['episode_number'] && (int) $r['data']['season_number'] > 0 ? 'Season ' . $r['data']['season_number'] . ' premiere' : 'S' . $r['data']['season_number'] . ' · E' . $r['data']['episode_number']; }
            if ( $f['search'] && false === mb_stripos( $event['title'] . ' ' . $event['show_title'], $f['search'] ) ) { continue; }
            foreach ( $services as $service ) { ++$counts[ $service['uuid'] ]; }
            if ( $f['service'] && ! in_array( $f['service'], array_column( $services, 'uuid' ), true ) ) { continue; }
            $event['record'] = $r; $events[] = $event;
        }
        $total = count( $events ); $pages = max( 1, (int) ceil( $total / 60 ) ); $f['page'] = min( $f['page'], $pages );
        return array( 'filters' => $f, 'directory' => $directory, 'counts' => $counts, 'events' => array_slice( $events, ( $f['page'] - 1 ) * 60, 60 ), 'total' => $total, 'pages' => $pages, 'days' => count( array_unique( array_column( $events, 'date' ) ) ) );
    }
    private static function service_logo( array $service ): string {
        return $service['logo'] ? '<img src="' . esc_url( $service['logo'] ) . '" alt="" width="90" height="42" loading="lazy">' : '<span class="release-service-initial" aria-hidden="true">' . esc_html( mb_substr( $service['title'], 0, 1 ) ) . '</span>';
    }
    public static function markup( array $args, string $base = '' ): string {
        $d = self::data( $args ); $f = $d['filters']; $month = new \DateTimeImmutable( $f['month'] . '-01', wp_timezone() ); $base = $base ?: self::hub_url();
        $directory = $d['directory']; $selected = $f['service'] ? $directory[ $f['service'] ] : null;
        ob_start();
        include dirname( MSC_FILE ) . '/views/upcoming-releases.php';
        return ob_get_clean();
    }
    public static function shortcode( $atts = array() ): string {
        $args = array_intersect_key( (array) $atts, array_flip( array( 'month', 'type', 'service', 'view' ) ) );
        foreach ( array( 'month', 'type', 'service', 'search', 'view', 'past', 'page' ) as $key ) { if ( isset( $_GET[ 'release_' . $key ] ) ) { $args[ $key ] = wp_unslash( $_GET[ 'release_' . $key ] ); } }
        $base = is_singular() ? (string) get_permalink() : self::hub_url();
        try { return '<section id="upcoming-releases" class="msc-upcoming" data-endpoint="' . esc_url( rest_url( 'msc/v1/upcoming-releases' ) ) . '" data-base="' . esc_url( $base ) . '">' . self::markup( $args, $base ) . '</section>'; }
        catch ( \Throwable $e ) { return '<p class="empty-state">' . esc_html( $e->getMessage() ) . ' <a href="' . esc_url( self::hub_url() ) . '">Reset release filters</a></p>'; }
    }
    public static function boot(): void {
        add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) ); ReleasePages::boot();
        add_action( 'rest_api_init', static function () { register_rest_route( 'msc/v1', '/upcoming-releases', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static function ( $request ) {
            try { $args = array(); foreach ( array( 'month', 'type', 'service', 'search', 'view', 'past', 'page' ) as $key ) { if ( isset( $request[ $key ] ) ) { $args[ $key ] = $request[ $key ]; } }
                $base = $request['base'] ?? ''; if ( ! is_string( $base ) || $base && ! wp_validate_redirect( $base, '' ) ) { throw new \InvalidArgumentException( 'Invalid release page URL.' ); }
                return array( 'html' => self::markup( $args, $base ), 'credits_html' => Attribution::footer() );
            } catch ( \Throwable $e ) { return new \WP_Error( 'msc_upcoming', $e->getMessage(), array( 'status' => 400 ) ); }
        } ) ); } );
        add_action( 'wp_enqueue_scripts', static function () {
            if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), self::SHORTCODE ) ) { wp_enqueue_style( 'msc-upcoming', plugins_url( 'assets/upcoming-releases.css', MSC_FILE ), array(), MSC_VERSION ); wp_enqueue_script( 'msc-upcoming', plugins_url( 'assets/upcoming-releases.js', MSC_FILE ), array(), MSC_VERSION, true ); }
        } );
    }
}
