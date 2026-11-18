<?php
namespace MSC;

/** Add the curated MCU example once, with optional links to existing catalog records. */
final class TimelineDemo {
    private const OPTION = 'msc_mcu_timeline_example';

    public static function seed(): ?array {
        if ( ! Publication::global_allowed() ) { throw new \RuntimeException( 'Network publication permission is required.' ); }
        Timelines::enroll();
        $site = self::site();
        if ( ! $site ) { return null; }
        return Database::lock( 'mcu-timeline-example', static function () use ( $site ) {
            wp_cache_delete( get_current_network_id() . ':' . self::OPTION, 'site-options' );
            $saved = get_network_option( get_current_network_id(), self::OPTION, '' );
            if ( $saved ) { return Plugin::store()->get( $saved ); }
            switch_to_blog( $site );
            try {
                Plugin::register_posts();
                return Database::transaction( static function () use ( $site ) {
                    $model = json_decode( file_get_contents( dirname( MSC_FILE ) . '/data/mcu-timeline.json' ), true, 32, JSON_THROW_ON_ERROR );
                    foreach ( $model['entries'] as &$entry ) {
                        $entry['item'] = self::catalog_title( Timelines::TRACKS[ $entry['kind'] ], array( $entry['catalog_title'] ) );
                    }
                    unset( $entry );
                    $store = Plugin::store();
                    $record = $store->create( Timelines::TYPE, array(
                        'title' => 'MCU In-Universe Timeline',
                        'slug' => 'mcu-in-universe',
                        'content' => '<p>Follow selected MCU movies and TV seasons by when their main stories take place. The phase colors track each production’s MCU phase. Branch realities and the TVA have a separate section.</p>',
                        'additional_metadata' => array( 'timeline_sources' => array(
                            array( 'url' => 'https://www.marvel.com/articles/movies/mcu-timeline-order-disney-plus', 'label' => 'Marvel’s official story order' ),
                            array( 'url' => 'https://www.disneyplus.com/explore/articles/marvel-movies-in-order', 'label' => 'Disney+ phase guide' ),
                        ) ),
                        'franchise' => self::catalog_title( 'msc_franchise', array( 'Marvel Cinematic Universe', 'MCU' ) ),
                        'chronology' => $model,
                    ) );
                    $id = (int) $record['id'];
                    Plugin::materializer()->sync( $id, $site );
                    Publication::publish( $id, 'global', (int) $store->mapping( $id )['local_version'] );
                    if ( ! update_network_option( get_current_network_id(), self::OPTION, $record['uuid'] ) ) {
                        throw new \RuntimeException( 'Could not save the example’s identifier. Retry the timeline command.' );
                    }
                    return $record;
                } );
            } finally {
                restore_current_blog();
            }
        } );
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

    private static function catalog_title( string $type, array $titles ): string {
        if ( ! Schema::enabled( $type ) ) { return ''; }
        $matches = array();
        foreach ( $titles as $title ) {
            $posts = get_posts( array(
                'post_type' => $type, 'post_status' => array( 'publish', 'draft' ), 'title' => $title,
                'posts_per_page' => 3,
            ) );
            foreach ( $posts as $post ) {
                $record = Plugin::store()->by_post( $post->ID );
                if ( $record && 'active' === $record['state'] ) { $matches[ $record['uuid'] ] = true; }
            }
        }
        return count( $matches ) === 1 ? (string) array_key_first( $matches ) : '';
    }
}
