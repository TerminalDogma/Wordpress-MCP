<?php
namespace MSC;

/** Install the MCU example and connect it to the network catalog. */
final class TimelineDemo {
    private const OPTION = 'msc_mcu_timeline_example';
    private const LINKS_VERSION = 'msc_mcu_timeline_links_version';

    public static function seed(): ?array {
        if ( ! Publication::global_allowed() ) { throw new \RuntimeException( 'Network publication permission is required.' ); }
        Timelines::enroll();
        $site = self::site();
        if ( ! $site ) { return null; }
        return Database::lock( 'mcu-timeline-example', static function () use ( $site ) {
            $network = get_current_network_id();
            foreach ( array( self::OPTION, self::LINKS_VERSION ) as $option ) {
                wp_cache_delete( $network . ':' . $option, 'site-options' );
            }
            $saved = get_network_option( $network, self::OPTION, '' );
            $existing = $saved ? Plugin::store()->get( $saved ) : null;
            if ( $saved && ( ! $existing || 'active' !== $existing['state'] || get_network_option( $network, self::LINKS_VERSION ) ) ) { return $existing; }
            switch_to_blog( $site );
            try {
                Plugin::register_posts();
                $record = Database::transaction( static fn() => self::install( $existing, $site ) );
                if ( $existing && $record['version'] !== $existing['version'] ) { Propagation::send( $record ); }
                return $record;
            } finally {
                restore_current_blog();
            }
        } );
    }

    private static function data( string $name ): array {
        return json_decode( file_get_contents( dirname( MSC_FILE ) . '/data/' . $name . '.json' ), true, 32, JSON_THROW_ON_ERROR );
    }

    private static function install( ?array $existing, int $site ): array {
        $store = Plugin::store();
        $example = self::data( 'mcu-timeline' );
        $starter = self::data( 'mcu-catalog-example' );
        $catalog = array();
        $members = array( 'movies' => array(), 'shows' => array() );
        $items = array();
        foreach ( $example['entries'] as $row ) {
            $name = $row['catalog_title'];
            $type = Timelines::LEGACY_TYPES[ $row['kind'] ];
            $key = $type . ':' . $name;
            if ( ! array_key_exists( $key, $catalog ) ) {
                $record = self::catalog_record( $type, array( $name ), $starter[ $name ]['external_ids']['imdb'] ?? '' );
                if ( null === $record && isset( $starter[ $name ] ) && Schema::enabled( $type ) ) {
                    $record = self::starter_title( $type, $name, $starter[ $name ], $site );
                }
                if ( $record && 'active' === $record['state'] && 'msc_movie' === $type && isset( $starter[ $name ]['credits'] ) ) {
                    self::add_demo_roles( $record, $starter[ $name ]['credits'] );
                }
                $catalog[ $key ] = $record && 'active' === $record['state'] ? $record['uuid'] : '';
            }
            if ( $uuid = $catalog[ $key ] ) {
                $items[ $row['title'] ] = $uuid;
                $members[ 'movie' === $row['kind'] ? 'movies' : 'shows' ][] = $uuid;
            }
        }

        $franchise = self::franchise( $members, $site );
        $model = Timelines::model( $existing['data']['chronology'] ?? $example );
        $changed = false;
        foreach ( $model['entries'] ?? array() as $index => $entry ) {
            $uuid = $entry['item'] ?? '';
            if ( ! $uuid && isset( $items[ $entry['title'] ] ) ) {
                $model['entries'][ $index ]['item'] = $items[ $entry['title'] ];
                $changed = true;
            }
        }
        if ( $existing ) {
            $values = array();
            if ( $changed ) { $values['chronology'] = $model; }
            if ( $franchise && empty( $existing['data']['franchise'] ) ) { $values['franchise'] = $franchise; }
            $record = $values ? $store->update( $existing['id'], $values, (int) $existing['version'] ) : $existing;
        } else {
            $record = $store->create( Timelines::TYPE, array(
                'title' => 'MCU In-Universe Timeline', 'slug' => 'mcu-in-universe',
                'content' => '<p>Follow selected MCU movies and TV seasons by when their main stories take place. The phase colors track each production’s MCU phase. Branch realities and the TVA have a separate section.</p>',
                'additional_metadata' => array( 'timeline_sources' => array(
                    array( 'url' => 'https://www.marvel.com/articles/movies/mcu-timeline-order-disney-plus', 'label' => 'Marvel’s official story order' ),
                    array( 'url' => 'https://www.disneyplus.com/explore/articles/marvel-movies-in-order', 'label' => 'Disney+ phase guide' ),
                ) ),
                'franchise' => $franchise, 'chronology' => $model,
            ) );
            self::publish( $record, $site );
            if ( ! update_network_option( get_current_network_id(), self::OPTION, $record['uuid'] ) ) {
                throw new \RuntimeException( 'Could not save the example’s identifier. Retry the timeline command.' );
            }
        }
        update_network_option( get_current_network_id(), self::LINKS_VERSION, 1 );
        return $record;
    }

    private static function add_demo_roles( array $record, array $credits ): void {
        if ( array_key_exists( 'cast_roles', $record['data'] ) || ! isset( Schema::get( 'msc_movie' )['fields']['cast_roles'] ) || ! Schema::enabled( 'msc_movie' ) ) { return; }
        $names = array();
        $store = Plugin::store();
        foreach ( $record['data']['actors'] ?? array() as $id ) {
            $person = $store->get( $id );
            if ( ! empty( $person['data']['title'] ) ) {
                $name = $person['data']['title'];
                $names[ PersonIdentity::name( $name ) ] = $name;
            }
        }
        $lines = array();
        foreach ( $credits as $actor => $role ) {
            $name = $names[ PersonIdentity::name( $actor ) ] ?? '';
            if ( $name ) { $lines[] = $name . ' | ' . $role; }
        }
        if ( $lines ) {
            $saved = $store->update( $record['id'], array( 'cast_roles' => implode( "\n", $lines ) ), (int) $record['version'] );
            Propagation::send( $saved );
        }
    }

    private static function starter_title( string $type, string $name, array $fields, int $site ): array {
        $store = Plugin::store();
        $credits = $fields['credits'] ?? array();
        unset( $fields['credits'] );
        $actors = $characters = array();
        if ( $credits && Schema::enabled( 'msc_person' ) ) {
            foreach ( $credits as $actor => $role ) {
                $person = self::catalog_record( 'msc_person', array( $actor ) );
                if ( null === $person ) { $person = $store->create( 'msc_person', array( 'title' => $actor, 'professions' => array( 'Actor' ) ) ); }
                if ( $person && 'active' === $person['state'] ) {
                    $actors[] = $person['uuid'];
                    $characters[] = $actor . ' | ' . $role;
                }
            }
        }
        if ( $actors ) { $fields['actors'] = $actors; }
        if ( $characters ) { $fields['cast_roles'] = implode( "\n", $characters ); }
        $record = $store->create( $type, array( 'title' => $name ) + array_intersect_key( $fields, Schema::get( $type )['fields'] ) );
        self::publish( $record, $site );
        return $record;
    }

    private static function franchise( array $members, int $site ): string {
        if ( ! Schema::enabled( 'msc_franchise' ) ) { return ''; }
        $store = Plugin::store();
        $record = self::catalog_record( 'msc_franchise', array( 'Marvel Cinematic Universe', 'MCU' ) );
        if ( false === $record || ( $record && 'active' !== $record['state'] ) ) { return ''; }
        foreach ( $members as $field => &$ids ) {
            $ids = array_values( array_unique( array_merge( $record['data'][ $field ] ?? array(), $ids ) ) );
        }
        unset( $ids );
        if ( $record ) {
            $values = array_filter( $members, static fn( $ids, $field ) => $ids !== ( $record['data'][ $field ] ?? array() ), ARRAY_FILTER_USE_BOTH );
            if ( $values ) {
                $record = $store->update( $record['id'], $values, (int) $record['version'] );
                Propagation::send( $record );
            }
        } else {
            $record = $store->create( 'msc_franchise', array(
                'title' => 'Marvel Cinematic Universe', 'slug' => 'marvel-cinematic-universe',
                'content' => '<p>The Marvel Cinematic Universe connects movies and television series through shared characters, events and worlds. Browse the productions below or open the in-universe timeline for their story chronology.</p>',
                'theme_color' => '#b3374f',
            ) + $members );
            self::publish( $record, $site );
        }
        return $record['uuid'];
    }

    private static function publish( array $record, int $site ): void {
        $id = (int) $record['id'];
        Plugin::materializer()->sync( $id, $site );
        Publication::publish( $id, 'global', (int) Plugin::store()->mapping( $id )['local_version'] );
    }

    private static function site(): int {
        $sites = get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'spam' => 0, 'archived' => 0 ) );
        $first = 0;
        foreach ( $sites as $site ) {
            $id = (int) $site->blog_id;
            if ( ! Schema::enabled( Timelines::TYPE, $id ) ) { continue; }
            if ( 'a' === NetworkSites::key( $site->path ) ) { return $id; }
            $first = $first ?: $id;
        }
        return $first;
    }

    /** Null means missing. False means ambiguous. Include archived matches to avoid duplicating them. */
    private static function catalog_record( string $type, array $titles, string $imdb = '' ): array|false|null {
        global $wpdb;
        $table = Database::table( 'records' );
        $args = array_merge( array( $type ), array_map( static fn( $title ) => mb_strtolower( $title ), $titles ) );
        $match = "LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(data,'$.title')))) IN (" . implode( ',', array_fill( 0, count( $titles ), '%s' ) ) . ')';
        if ( $imdb ) {
            $matches = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE type=%s AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.imdb'))=%s LIMIT 2", $type, $imdb ) );
            if ( $matches ) { return count( $matches ) === 1 ? Plugin::store()->get( (int) $matches[0] ) : false; }
        }
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE type=%s AND ($match) ORDER BY id LIMIT 3", ...$args ) );
        if ( count( $ids ) > 1 ) { return false; }
        if ( ! $ids ) { return null; }
        $record = Plugin::store()->get( (int) $ids[0] );
        $existing_id = $record['data']['external_ids']['imdb'] ?? '';
        return $imdb && $existing_id && $imdb !== $existing_id ? false : $record;
    }
}
