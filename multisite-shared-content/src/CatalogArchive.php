<?php
namespace MSC;

/** Public archive models use effective values and the same site rules as detail pages. */
final class CatalogArchive {
    public const PAGE_SIZE = 20;
    private static ?array $current = null;
    private static array $related = array();
    private const FIELDS = array( 'title', 'featured_image', 'poster', 'headshot', 'logo', 'release_date', 'premiere_date', 'release_dates', 'runtime', 'content_rating', 'genres', 'platforms', 'platform_ids', 'console_records', 'professions', 'country', 'network', 'streaming_service', 'status', 'release_status', 'number_of_seasons', 'number_of_episodes', 'parent_show', 'season_number', 'episode_number', 'headquarters', 'founded_date', 'manufacturer', 'platform_kind' );
    public static function spec( string $type ): array {
        $base = array( 'type' => $type, 'label' => Schema::get( $type )['label'], 'layout' => 'directory', 'description' => 'Browse profiles, key information and related titles.', 'facet' => 'country', 'facet_label' => 'Country', 'date' => '', 'noun' => 'profiles' );
        $types = array(
            'msc_movie' => array( 'layout' => 'movies', 'description' => 'Browse movies by title, genre and release year.', 'facet' => 'genres', 'facet_label' => 'Genre', 'date' => 'release_date', 'noun' => 'movies' ),
            'msc_show' => array( 'layout' => 'shows', 'description' => 'Find TV shows, series information and episode guides.', 'facet' => 'genres', 'facet_label' => 'Genre', 'date' => 'premiere_date', 'noun' => 'shows' ),
            'msc_game' => array( 'layout' => 'games', 'description' => 'Browse video games, platforms and release dates.', 'facet' => 'genres', 'facet_label' => 'Genre', 'date' => 'release_date', 'noun' => 'games' ),
            'msc_person' => array( 'layout' => 'people', 'description' => 'Find actors, directors, writers and other film and TV professionals.', 'facet' => 'professions', 'facet_label' => 'Profession', 'noun' => 'people' ),
            'msc_episode' => array( 'layout' => 'episodes', 'description' => 'Find episodes by title or parent TV show.', 'facet' => 'parent_show', 'facet_label' => 'TV Show', 'date' => 'release_date', 'noun' => 'episodes' ),
            'msc_console' => array( 'layout' => 'hardware', 'description' => 'Browse consoles and platforms, manufacturers and launch dates.', 'facet' => 'platform_kind', 'facet_label' => 'Platform type', 'date' => 'release_date', 'noun' => 'platforms' ),
        ); return array_replace( $base, $types[ $type ] ?? array() );
    }
    public static function related( string $uuid ): ?array {
        $key = get_current_blog_id() . ':' . $uuid; if ( ! array_key_exists( $key, self::$related ) ) { self::$related[ $key ] = Presentation::record( $uuid ); } return self::$related[ $key ];
    }
    /** Read records and overrides in batches, discarding body/excerpt data immediately. */
    private static function records( array $types, ?\WP_Term $term = null ): array {
        $types = array_values( array_filter( $types, static fn( $type ) => isset( Schema::all()[ $type ] ) && Schema::enabled( $type ) ) ); if ( ! $types ) { return array(); }
        global $wpdb; $records = Database::table( 'records' ); $maps = Database::table( 'maps' ); $overrides = Database::table( 'overrides' ); $site = get_current_blog_id(); $after = 0; $result = array(); $keys = array_fill_keys( self::FIELDS, true );
        do {
            $where = $term ? $wpdb->prepare( " AND EXISTS (SELECT 1 FROM $wpdb->term_relationships tr WHERE tr.object_id=p.ID AND tr.term_taxonomy_id=%d)", $term->term_taxonomy_id ) : '';
            $sql = "SELECT r.id,r.uuid,r.type,r.state,r.data,m.post_id,p.post_modified_gmt FROM $records r INNER JOIN $maps m ON m.record_id=r.id AND m.site_id=%d INNER JOIN $wpdb->posts p ON p.ID=m.post_id WHERE r.id>%d AND r.state='active' AND m.status='publish' AND m.suppressed=0 AND p.post_status='publish' AND p.post_password='' AND r.type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")$where ORDER BY r.id LIMIT 200";
            $batch = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( array( $site, $after ), $types ) ), ARRAY_A ); if ( ! $batch ) { break; }
            $ids = array_map( 'intval', array_column( $batch, 'id' ) ); $local = array();
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT record_id,field,value FROM $overrides WHERE site_id=%d AND record_id IN (" . implode( ',', $ids ) . ')', $site ), ARRAY_A );
            foreach ( $rows as $row ) { if ( isset( $keys[ $row['field'] ] ) ) { $local[ $row['record_id'] ][ $row['field'] ] = json_decode( $row['value'], true, 64, JSON_THROW_ON_ERROR ); } }
            foreach ( $batch as $row ) {
                $after = (int) $row['id']; $global = array_intersect_key( json_decode( $row['data'], true, 64, JSON_THROW_ON_ERROR ), $keys );
                $r = array( 'id' => $after, 'uuid' => $row['uuid'], 'type' => $row['type'], 'state' => 'active', 'global' => $global, 'overrides' => $local[ $after ] ?? array(), 'mapping' => array( 'post_id' => (int) $row['post_id'] ), 'modified' => $row['post_modified_gmt'] ); $r['data'] = array_replace( $global, $r['overrides'] );
                if ( ! GamePolicy::accepts( $r ) ) { continue; } $r = GamePolicy::project( $r );
                if ( 'msc_game' === $r['type'] ) { $platforms = (array) ( $r['data']['platforms'] ?? array() ); foreach ( (array) ( $r['data']['console_records'] ?? array() ) as $uuid ) { $console = self::related( $uuid ); if ( $console && 'msc_console' === $console['type'] ) { $platforms[] = $console['data']['title']; } } $r['data']['platforms'] = array_values( array_unique( $platforms ) ); }
                $r['catalog_date'] = ReleaseCalendar::date( $r['data'][ self::spec( $r['type'] )['date'] ] ?? '' ); $result[] = $r;
            }
        } while ( count( $batch ) === 200 ); return $result;
    }
    private static function input( array $input, string $key, string $default = '' ): string { return isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $input[ $key ] ) ) ), 0, 160 ) : $default; }
    public static function build( array $types, array $input = array(), ?\WP_Term $term = null ): array {
        self::$related = array(); $spec = 1 === count( $types ) ? self::spec( $types[0] ) : array( 'type' => '', 'label' => 'Catalog', 'layout' => 'mixed', 'description' => 'Browse published titles in this category.', 'facet' => 'type', 'facet_label' => 'Content type', 'date' => 'release_date', 'noun' => 'titles' );
        $spec['url'] = $term ? get_term_link( $term ) : get_post_type_archive_link( $types[0] ); $spec['key'] = $term ? 'tax:' . $term->term_taxonomy_id : 'type:' . $types[0]; if ( $term ) { $spec['label'] = $term->name; }
        $sorts = array( 'updated' => 'Recently updated', 'title_asc' => 'Title: A–Z', 'title_desc' => 'Title: Z–A' ); if ( $spec['date'] ) { $sorts += array( 'date_new' => 'Release date: newest', 'date_old' => 'Release date: oldest' ); }
        $sort = self::input( $input, 'catalog_sort', in_array( $spec['layout'], array( 'people', 'directory', 'hardware' ), true ) ? 'title_asc' : 'updated' ); if ( ! isset( $sorts[ $sort ] ) ) { $sort = array_key_first( $sorts ); }
        $f = array( 'catalog_q' => self::input( $input, 'catalog_q' ), 'catalog_facet' => self::input( $input, 'catalog_facet' ), 'catalog_year' => $spec['date'] ? self::input( $input, 'catalog_year' ) : '', 'catalog_platform' => in_array( 'msc_game', $types, true ) ? self::input( $input, 'catalog_platform' ) : '', 'catalog_sort' => $sort );
        $all = self::records( $types, $term ); $facets = array(); $years = array(); $platforms = array(); $filtered = array(); $next = null; $today = wp_date( 'Y-m-d' );
        foreach ( $all as $r ) {
            $values = 'type' === $spec['facet'] ? array( $r['type'] ) : (array) ( $r['data'][ $spec['facet'] ] ?? array() );
            if ( 'parent_show' === $spec['facet'] ) { $values = array(); $parent = ! empty( $r['data']['parent_show'] ) ? self::related( $r['data']['parent_show'] ) : null; if ( $parent && 'msc_show' === $parent['type'] ) { $values[] = $parent['uuid']; $facets[ $parent['uuid'] ] = $parent['data']['title']; } }
            foreach ( $values as $value ) { if ( ! is_string( $value ) || '' === $value ) { continue; } if ( 'parent_show' !== $spec['facet'] ) { $facets[ $value ] = 'type' === $spec['facet'] ? Schema::get( $value )['label'] : $value; } }
            $year = $r['catalog_date'] ? substr( $r['catalog_date'], 0, 4 ) : ''; if ( $year ) { $years[ $year ] = $year; }
            foreach ( (array) ( $r['data']['platforms'] ?? array() ) as $platform ) { if ( is_string( $platform ) && $platform ) { $platforms[ $platform ] = $platform; } }
            if ( $r['catalog_date'] >= $today && ( ! $next || array( $r['catalog_date'], $r['data']['title'] ?? '' ) < array( $next['catalog_date'], $next['data']['title'] ?? '' ) ) ) { $next = $r; }
            if ( $f['catalog_q'] && false === mb_stripos( $r['data']['title'] ?? '', $f['catalog_q'] ) || $f['catalog_facet'] && ! in_array( $f['catalog_facet'], $values, true ) || $f['catalog_year'] && $f['catalog_year'] !== $year || $f['catalog_platform'] && ! in_array( $f['catalog_platform'], (array) ( $r['data']['platforms'] ?? array() ), true ) ) { continue; } $filtered[] = $r;
        }
        natcasesort( $facets ); krsort( $years ); natcasesort( $platforms );
        usort( $filtered, static function ( $a, $b ) use ( $sort ) {
            $title = strnatcasecmp( $a['data']['title'] ?? '', $b['data']['title'] ?? '' );
            if ( str_starts_with( $sort, 'date_' ) ) { if ( (bool) $a['catalog_date'] !== (bool) $b['catalog_date'] ) { return $a['catalog_date'] ? -1 : 1; } $cmp = strcmp( $a['catalog_date'], $b['catalog_date'] ) * ( 'date_new' === $sort ? -1 : 1 ); if ( $cmp ) { return $cmp; } }
            elseif ( 'updated' === $sort && $a['modified'] !== $b['modified'] ) { return strcmp( $b['modified'], $a['modified'] ); }
            return ( 'title_desc' === $sort ? -$title : $title ) ?: ( $a['id'] <=> $b['id'] );
        } );
        return array( 'spec' => $spec, 'filters' => $f, 'sorts' => $sorts, 'facets' => $facets, 'years' => $years, 'platforms' => $platforms, 'records' => $filtered, 'total' => count( $all ), 'next' => $next );
    }
    public static function current(): ?array { return self::$current; }
    public static function url( array $model, array $replace = array() ): string { return add_query_arg( array_filter( array_replace( $model['filters'], $replace ), 'strlen' ), $model['spec']['url'] ); }
    public static function boot(): void {
        add_action( 'pre_get_posts', static function ( $q ) {
            if ( is_admin() || ! $q->is_main_query() || $q->is_feed() || 'msc-screening-room' !== get_template() ) { return; }
            $types = array(); $term = null;
            if ( $q->is_post_type_archive() ) { $types = array_values( array_intersect( (array) $q->get( 'post_type' ), array_keys( Schema::all() ) ) ); }
            elseif ( $q->is_tax() ) { $object = $q->get_queried_object(); if ( $object instanceof \WP_Term && isset( Taxonomies::definitions()[ $object->taxonomy ] ) ) { $term = $object; $types = array_values( array_intersect( get_taxonomy( $term->taxonomy )->object_type, array_keys( Schema::all() ) ) ); } }
            $types = array_values( array_filter( $types, static fn( $type ) => Schema::enabled( $type ) ) ); if ( ! $types ) { return; }
            self::$current = self::build( $types, $_GET, $term ); $ids = array_column( array_column( self::$current['records'], 'mapping' ), 'post_id' );
            $q->set( 'post__in', $ids ?: array( 0 ) ); $q->set( 'orderby', 'post__in' ); $q->set( 'posts_per_page', self::PAGE_SIZE ); $q->set( 'post_status', 'publish' ); $q->set( 'ignore_sticky_posts', true );
        }, 50 );
    }
}
