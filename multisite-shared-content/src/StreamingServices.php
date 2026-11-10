<?php
namespace MSC;

/** Published service entries and portable logo assets; no public API requests. */
final class StreamingServices {
    public static function brands(): array {
        return array(
            'netflix' => array( 'Netflix', 'https://www.netflix.com/', array() ),
            'primevideo' => array( 'Prime Video', 'https://www.primevideo.com/', array( 'Amazon Prime Video', 'Amazon Prime' ) ),
            'disneyplus' => array( 'Disney+', 'https://www.disneyplus.com/', array( 'Disney Plus' ) ),
            'hulu' => array( 'Hulu', 'https://www.hulu.com/', array() ),
            'appletv' => array( 'Apple TV', 'https://tv.apple.com/', array( 'Apple TV+', 'Apple TV Plus' ) ),
            'hbomax' => array( 'HBO Max', 'https://www.hbomax.com/', array( 'Max' ) ),
            'paramountplus' => array( 'Paramount+', 'https://www.paramountplus.com/', array( 'Paramount Plus' ) ),
            'crunchyroll' => array( 'Crunchyroll', 'https://www.crunchyroll.com/', array() ),
        );
    }
    public static function name( string $name ): string { return mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $name ) ) ); }
    public static function directory(): array {
        if ( ! Schema::enabled( 'msc_streaming' ) ) { return array(); }
        $out = array();
        $posts = get_posts( array( 'post_type' => 'msc_streaming', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'has_password' => false ) );
        foreach ( $posts as $post ) {
            $g = Plugin::store()->by_post( $post->ID ); $r = $g ? Presentation::record( $g['id'] ) : null;
            if ( ! $r ) { continue; }
            $out[ $r['uuid'] ] = array( 'logo_ref' => $r['data']['logo'] ?? array(), 'uuid' => $r['uuid'], 'title' => $r['data']['title'], 'url' => Presentation::url( $r ), 'logo' => Media::url( (array) ( $r['data']['logo'] ?? array() ), 'medium' ), 'website' => $r['data']['official_website'] ?? ( self::brands()[ $r['data']['external_ids']['msc_streaming_service'] ?? '' ][1] ?? '' ), 'key' => $r['data']['external_ids']['msc_streaming_service'] ?? '' );
        }
        uasort( $out, static fn( $a, $b ) => array( ! isset( self::brands()[ $a['key'] ] ), $a['title'] ) <=> array( ! isset( self::brands()[ $b['key'] ] ), $b['title'] ) );
        return $out;
    }
    /** Explicit local empty assignments suppress inheritance, just like other relationships. */
    public static function for_record( array $r, array $directory, ?array $parent = null ): array {
        $own = array_key_exists( 'streaming_service_records', $r['overrides'] ?? array() ) || array_key_exists( 'streaming_service', $r['overrides'] ?? array() ) || ! empty( $r['data']['streaming_service_records'] ) || ! empty( $r['data']['streaming_service'] );
        if ( 'msc_episode' === $r['type'] && ! $own && $parent ) { $r = $parent; }
        $out = array();
        foreach ( (array) ( $r['data']['streaming_service_records'] ?? array() ) as $uuid ) { if ( isset( $directory[ $uuid ] ) ) { $out[ $uuid ] = $directory[ $uuid ]; } }
        // An explicit empty relationship override also suppresses legacy name matching.
        if ( array_key_exists( 'streaming_service_records', $r['overrides'] ?? array() ) && empty( $r['overrides']['streaming_service_records'] ) ) { return array_values( $out ); }
        $names = (array) ( $r['data']['streaming_service'] ?? array() );
        if ( ! $names && in_array( $r['type'], array( 'msc_show', 'msc_episode' ), true ) ) { $names = (array) ( $r['data']['network'] ?? array() ); }
        foreach ( $names as $name ) {
            if ( ! is_string( $name ) ) { continue; } $matches = array();
            foreach ( $directory as $uuid => $service ) {
                $known = self::brands()[ $service['key'] ] ?? array( '', '', array() ); $aliases = array_merge( array( $service['title'] ), $known[2] );
                if ( in_array( self::name( $name ), array_map( array( self::class, 'name' ), $aliases ), true ) ) { $matches[ $uuid ] = $service; }
            }
            if ( 1 === count( $matches ) ) { $out += $matches; }
        }
        return array_values( $out );
    }
    private static function logo( string $key, array $brand ): array {
        $bytes = file_get_contents( dirname( MSC_FILE ) . '/assets/services/' . $key . '.png' );
        if ( false === $bytes ) { throw new \RuntimeException( 'A bundled service logo is missing.' ); }
        $upload = wp_upload_bits( $key . '-logo.png', null, $bytes );
        if ( $upload['error'] ) { throw new \RuntimeException( 'Could not save the service logo.' ); }
        $id = wp_insert_attachment( array( 'post_title' => $brand[0] . ' logo', 'post_status' => 'inherit', 'post_mime_type' => 'image/png' ), $upload['file'], 0, true );
        if ( is_wp_error( $id ) ) { wp_delete_file( $upload['file'] ); throw new \RuntimeException( 'Could not register the service logo.' ); }
        require_once ABSPATH . 'wp-admin/includes/image.php'; wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
        update_post_meta( $id, '_wp_attachment_image_alt', $brand[0] );
        update_post_meta( $id, '_msc_manual_image_source', array( 'source_page' => $brand[1], 'credit' => $brand[0] . ' logo and trademark belong to their respective owner.' ) );
        return array( 'site_id' => get_current_blog_id(), 'attachment_id' => (int) $id );
    }
    /** One-time, additive directory setup. Existing publication choices are retained. */
    public static function seed(): void {
        if ( ! Publication::global_allowed() || ! current_user_can( 'msc_create_global' ) || ! current_user_can( 'upload_files' ) || get_network_option( get_current_network_id(), 'msc_streaming_directory_version', 0 ) >= 1 ) { return; }
        Database::lock( 'streaming-directory', static function () {
            if ( get_network_option( get_current_network_id(), 'msc_streaming_directory_version', 0 ) >= 1 ) { return; }
            $sites = array_values( array_filter( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'spam' => 0, 'archived' => 0 ) ), static fn( $site ) => Schema::enabled( 'msc_streaming', (int) $site->blog_id ) ) );
            if ( ! $sites ) { return; }
            switch_to_blog( (int) $sites[0]->blog_id );
            try {
                $store = Plugin::store(); global $wpdb;
                foreach ( self::brands() as $key => $brand ) {
                    $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,data FROM ' . Database::table( 'records' ) . " WHERE type='msc_streaming' AND state='active' AND (JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.msc_streaming_service'))=%s OR LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(data,'$.title')))) IN (" . implode( ',', array_fill( 0, count( $brand[2] ) + 1, '%s' ) ) . ')) LIMIT 3', $key, self::name( $brand[0] ), ...array_map( array( self::class, 'name' ), $brand[2] ) ), ARRAY_A );
                    if ( count( $rows ) > 1 ) { continue; }
                    $existing = $rows ? $store->get( (int) $rows[0]['id'] ) : null;
                    $values = array();
                    if ( ! $existing || empty( $existing['data']['logo'] ) ) { $values['logo'] = self::logo( $key, $brand ); }
                    if ( ! $existing ) {
                        // Known public brand links are resolved from the directory; setup needs no DNS/API calls.
                        $r = $store->create( 'msc_streaming', $values + array( 'title' => $brand[0], 'external_ids' => array( 'msc_streaming_service' => $key ) ) );
                        Plugin::materializer()->sync( (int) $r['id'], get_current_blog_id() ); $m = $store->mapping( (int) $r['id'] ); Publication::publish( (int) $r['id'], 'global', (int) $m['local_version'] );
                    } else {
                        if ( empty( $existing['data']['external_ids']['msc_streaming_service'] ) ) { $values['external_ids'] = ( $existing['data']['external_ids'] ?? array() ) + array( 'msc_streaming_service' => $key ); }
                        if ( $values ) { $store->update( (int) $existing['id'], $values, (int) $existing['version'] ); }
                    }
                }
                update_network_option( get_current_network_id(), 'msc_streaming_directory_version', 1 );
            } finally { restore_current_blog(); }
        } );
    }
}
