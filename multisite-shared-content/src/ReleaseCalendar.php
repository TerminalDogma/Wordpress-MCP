<?php
namespace MSC;

/** Date-only events indexed from the effective fields of published local records. */
final class ReleaseCalendar {
    public const FIELDS = array(
        'msc_movie' => array( 'release_date' => 'Theatrical', 'digital_release_date' => 'Digital', 'streaming_release_date' => 'Streaming', 'physical_release_date' => 'Disc' ),
        'msc_show' => array( 'premiere_date' => 'TV premiere', 'finale_date' => 'TV finale' ),
        'msc_episode' => array( 'release_date' => 'Episode' ), 'msc_game' => array( 'release_date' => 'Video Game' ),
    );
    public static function date( $value ): string {
        if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) { return ''; }
        $d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) ); return $d && $d->format( 'Y-m-d' ) === $value ? $value : '';
    }
    public static function events( array $record, int $site ): array {
        if ( ! isset( self::FIELDS[ $record['type'] ] ) || $site && ( ! Schema::enabled( $record['type'], $site ) || ! GamePolicy::accepts( $record, $site ) ) ) { return array(); }
        if ( $site ) { $record = GamePolicy::project( $record, $site ); } $data = $record['data']; $events = array();
        if ( 'msc_game' === $record['type'] && ! array_key_exists( 'release_date', $record['overrides'] ?? array() ) ) {
            foreach ( (array) ( $data['release_dates'] ?? array() ) as $row ) {
                if ( ! is_array( $row ) || 'day' !== ( $row['precision'] ?? 'day' ) || ! self::date( $row['date'] ?? '' ) || $site && ! GamePolicy::platform_allowed( $row['platform_id'] ?? 0, $row['platform'] ?? '', $site ) ) { continue; }
                $region = sanitize_text_field( $row['region'] ?? '' ); $key = $row['date'] . '|' . $region;
                if ( ! isset( $events[ $key ] ) ) { $events[ $key ] = array( 'date' => $row['date'], 'kind' => 'Video Game', 'field' => 'release_dates', 'region' => $region, 'platforms' => array() ); }
                if ( ! empty( $row['platform'] ) ) { $events[ $key ]['platforms'][] = sanitize_text_field( $row['platform'] ); }
            }
        }
        if ( ! $events ) {
            foreach ( self::FIELDS[ $record['type'] ] as $field => $kind ) { $date = self::date( $data[ $field ] ?? '' ); if ( $date ) { $events[ $field ] = array( 'date' => $date, 'kind' => $kind, 'field' => $field, 'region' => '', 'platforms' => 'msc_game' === $record['type'] ? (array) ( $data['platforms'] ?? array() ) : array() ); } }
        }
        foreach ( $events as &$event ) { $event['platforms'] = array_values( array_unique( $event['platforms'] ) ); $event['id'] = hash( 'sha256', $record['uuid'] . '|' . $event['date'] . '|' . $event['field'] . '|' . $event['region'] ); } unset( $event ); return array_values( $events );
    }
    public static function index( array $record, int $site, array $data ): void {
        if ( ! isset( self::FIELDS[ $record['type'] ] ) ) { return; } $record['global'] = $record['data']; $record['data'] = $data; $record['overrides'] = $site && 'msc_game' === $record['type'] ? Plugin::store()->overrides( (int) $record['id'], $site ) : array();
        global $wpdb; foreach ( self::events( $record, $site ) as $event ) { Database::check( $wpdb->replace( Database::table( 'lookup' ), array( 'record_id' => $record['id'], 'site_id' => $site, 'field' => 'cal:' . substr( $event['id'], 0, 40 ), 'text_value' => $event['date'], 'number_value' => null ) ) ); }
    }
    public static function month( $month ): string {
        if ( ! is_string( $month ) || ! preg_match( '/^(19[0-9]{2}|20[0-9]{2}|21[0-9]{2}|2200)-(0[1-9]|1[0-2])$/D', $month ) ) { throw new \InvalidArgumentException( 'Choose a calendar month between 1900 and 2200.' ); } return $month;
    }
    public static function data( string $month, string $type = '', string $platform = '', string $region = '' ): array {
        self::month( $month ); $types = array_values( array_filter( array_keys( self::FIELDS ), static fn( $t ) => Schema::enabled( $t ) ) );
        if ( $type && ! in_array( $type, $types, true ) ) { throw new \InvalidArgumentException( 'This content type is not available on this site.' ); } if ( $type ) { $types = array( $type ); }
        $result = array( 'month' => $month, 'events' => array(), 'platforms' => array(), 'regions' => array(), 'types' => $types ); if ( ! $types ) { return $result; }
        global $wpdb; $lookup = Database::table( 'lookup' ); $records = Database::table( 'records' ); $maps = Database::table( 'maps' ); $first = $month . '-01'; $last = ( new \DateTimeImmutable( $first ) )->modify( '+1 month' )->format( 'Y-m-d' );
        $args = array_merge( array( get_current_blog_id(), $first, $last ), $types );
        $sql = "SELECT DISTINCT r.id FROM $lookup l INNER JOIN $records r ON r.id=l.record_id INNER JOIN $maps m ON m.record_id=r.id AND m.site_id=l.site_id INNER JOIN $wpdb->posts p ON p.ID=m.post_id WHERE l.site_id=%d AND l.field LIKE 'cal:%%' AND l.text_value>=%s AND l.text_value<%s AND r.state='active' AND m.status='publish' AND m.suppressed=0 AND p.post_status='publish' AND p.post_password='' AND r.type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY r.id';
        $ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) );
        foreach ( $ids as $id ) {
            $r = Presentation::record( (int) $id ); if ( ! $r ) { continue; }
            foreach ( self::events( $r, get_current_blog_id() ) as $event ) {
                if ( substr( $event['date'], 0, 7 ) !== $month ) { continue; } $result['platforms'] = array_merge( $result['platforms'], $event['platforms'] ); if ( $event['region'] ) { $result['regions'][] = $event['region']; }
                if ( $platform && ! in_array( $platform, $event['platforms'], true ) || $region && $region !== $event['region'] ) { continue; }
                Attribution::collect( $r, array( $event['field'] ) );
                $event += array( 'title' => $r['data']['title'] ?? '(Untitled)', 'url' => Presentation::url( $r ), 'type' => $r['type'], 'uuid' => $r['uuid'] ); $result['events'][] = $event;
            }
        }
        foreach ( array( 'platforms', 'regions' ) as $key ) { $result[ $key ] = array_values( array_unique( $result[ $key ] ) ); natcasesort( $result[ $key ] ); $result[ $key ] = array_values( $result[ $key ] ); }
        usort( $result['events'], static fn( $a, $b ) => array( $a['date'], $a['title'], $a['kind'], $a['id'] ) <=> array( $b['date'], $b['title'], $b['kind'], $b['id'] ) ); return $result;
    }
    private static function filters( array $args ): array {
        $month = $args['month'] ?? wp_date( 'Y-m' ); self::month( $month ); $out = array( 'month' => $month );
        foreach ( array( 'type', 'platform', 'region' ) as $key ) { $value = $args[ $key ] ?? ''; if ( ! is_string( $value ) || strlen( $value ) > 120 ) { throw new \InvalidArgumentException( 'Invalid calendar filter.' ); } $out[ $key ] = sanitize_text_field( $value ); } return $out;
    }
    private static function url( array $f ): string { return add_query_arg( array_filter( array( 'calendar_month' => $f['month'], 'release_type' => $f['type'], 'release_platform' => $f['platform'], 'release_region' => $f['region'] ), 'strlen' ), NetworkSites::calendar_url() ) . '#release-calendar'; }
    public static function markup( array $args ): string {
        $f = self::filters( $args ); $d = self::data( $f['month'], $f['type'], $f['platform'], $f['region'] ); $date = new \DateTimeImmutable( $f['month'] . '-01', wp_timezone() ); $prev = $f; $next = $f; $prev['month'] = $date->modify( '-1 month' )->format( 'Y-m' ); $next['month'] = $date->modify( '+1 month' )->format( 'Y-m' );
        ob_start(); echo '<div class="release-calendar-content"><div class="calendar-heading"><a class="calendar-month-link" href="' . esc_url( self::url( $prev ) ) . '" aria-label="Previous month">← Previous</a><h2 tabindex="-1">' . esc_html( wp_date( 'F Y', $date->getTimestamp(), wp_timezone() ) ) . '</h2><a class="calendar-month-link" href="' . esc_url( self::url( $next ) ) . '" aria-label="Next month">Next →</a></div><form class="calendar-filters" method="get" action="' . esc_url( NetworkSites::calendar_url() ) . '"><label>Month<input type="month" name="calendar_month" value="' . esc_attr( $f['month'] ) . '" min="1900-01" max="2200-12"></label><label>Content<select name="release_type"><option value="">All release types</option>';
        foreach ( array_keys( self::FIELDS ) as $type ) { if ( Schema::enabled( $type ) ) { echo '<option value="' . esc_attr( $type ) . '"' . selected( $f['type'], $type, false ) . '>' . esc_html( Schema::get( $type )['label'] ) . '</option>'; } } echo '</select></label>';
        if ( Schema::enabled( 'msc_game' ) ) { foreach ( array( 'platform' => 'Platform', 'region' => 'Region' ) as $key => $label ) { $options = $d[ $key . 's' ]; if ( $f[ $key ] && ! in_array( $f[ $key ], $options, true ) ) { $options[] = $f[ $key ]; } echo '<label>' . $label . '<select name="release_' . $key . '"><option value="">All ' . strtolower( $label ) . 's</option>'; foreach ( $options as $value ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $f[ $key ], $value, false ) . '>' . esc_html( $value ) . '</option>'; } echo '</select></label>'; } }
        echo '<button class="calendar-apply" type="submit">Show releases</button></form><p class="calendar-count" role="status">' . esc_html( count( $d['events'] ) . ' releases in ' . wp_date( 'F Y', $date->getTimestamp(), wp_timezone() ) ) . '</p>';
        $by_date = array(); foreach ( $d['events'] as $event ) { $by_date[ $event['date'] ][] = $event; }
        $start = (int) get_option( 'start_of_week', 0 ); $offset = ( (int) $date->format( 'w' ) - $start + 7 ) % 7; $days = (int) $date->format( 't' ); $today = wp_date( 'Y-m-d' );
        echo '<div class="calendar-grid" aria-label="Release dates">'; for ( $i = 0; $i < 7; ++$i ) { echo '<div class="calendar-weekday">' . esc_html( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' )[ ( $start + $i ) % 7 ] ) . '</div>'; }
        for ( $i = 0; $i < $offset; ++$i ) { echo '<div class="calendar-day calendar-empty" aria-hidden="true"></div>'; }
        for ( $day = 1; $day <= $days; ++$day ) { $iso = $f['month'] . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT ); echo '<section class="calendar-day' . ( $iso === $today ? ' is-today' : '' ) . '" data-date="' . esc_attr( $iso ) . '"><time datetime="' . $iso . '">' . $day . '</time>'; foreach ( $by_date[ $iso ] ?? array() as $event ) { echo '<a class="calendar-event type-' . esc_attr( $event['type'] ) . '" href="' . esc_url( $event['url'] ) . '"><span class="calendar-event-kind">' . esc_html( $event['kind'] ) . '</span><strong>' . esc_html( $event['title'] ) . '</strong>'; if ( $event['platforms'] || $event['region'] ) { echo '<small>' . esc_html( implode( ' · ', array_filter( array( implode( ', ', $event['platforms'] ), $event['region'] ) ) ) ) . '</small>'; } echo '</a>'; } echo '</section>'; }
        echo '</div>'; if ( ! $d['events'] ) { echo '<p class="empty-state">No published releases with exact dates match this month and these filters.</p>'; }
        echo '<p class="calendar-note">Dates update from this site’s catalog. Release windows without an exact day remain on the title’s page.</p></div>'; return ob_get_clean();
    }
    public static function shortcode(): string {
        $args = array( 'month' => $_GET['calendar_month'] ?? wp_date( 'Y-m' ), 'type' => $_GET['release_type'] ?? '', 'platform' => $_GET['release_platform'] ?? '', 'region' => $_GET['release_region'] ?? '' );
        try { return '<section id="release-calendar" class="release-calendar" data-endpoint="' . esc_url( rest_url( 'msc/v1/calendar' ) ) . '">' . self::markup( $args ) . '</section>'; } catch ( \Throwable $e ) { return '<p class="empty-state">' . esc_html( $e->getMessage() ) . '</p>'; }
    }
    public static function boot(): void {
        add_shortcode( 'msc_release_calendar', array( self::class, 'shortcode' ) );
        add_action( 'rest_api_init', static function () { register_rest_route( 'msc/v1', '/calendar', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static function ( $q ) { try { $f = self::filters( array( 'month' => $q['month'] ?? wp_date( 'Y-m' ), 'type' => $q['type'] ?? '', 'platform' => $q['platform'] ?? '', 'region' => $q['region'] ?? '' ) ); return array( 'month' => $f['month'], 'data' => self::data( $f['month'], $f['type'], $f['platform'], $f['region'] ), 'html' => self::markup( $f ), 'credits_html' => Attribution::footer() ); } catch ( \Throwable $e ) { return new \WP_Error( 'msc_calendar', $e->getMessage(), array( 'status' => 400 ) ); } } ) ); } );
        add_action( 'wp_enqueue_scripts', static function () { if ( is_page( (int) get_option( 'msc_calendar_page' ) ) || is_singular() && has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'msc_release_calendar' ) ) { wp_enqueue_style( 'msc-release-calendar', plugins_url( 'assets/calendar.css', MSC_FILE ), array(), MSC_VERSION ); wp_enqueue_script( 'msc-release-calendar', plugins_url( 'assets/calendar.js', MSC_FILE ), array(), MSC_VERSION, true ); } } );
    }
}
