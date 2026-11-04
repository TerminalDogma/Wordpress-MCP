<?php
namespace MSC;

/** Keyless public APIs, bounded responses, shared caches and provider cooldowns. */
final class OpenClient {
    private const ROOTS = array( 'tvmaze' => 'https://api.tvmaze.com/', 'wikidata' => 'https://www.wikidata.org/w/api.php', 'wikipedia' => 'https://en.wikipedia.org/w/api.php', 'commons' => 'https://commons.wikimedia.org/w/api.php' );
    public static function fresh( string $name ) {
        wp_cache_delete( $name, 'site-transient' );
        foreach ( array( '_site_transient_' . $name, '_site_transient_timeout_' . $name ) as $option ) { wp_cache_delete( get_current_network_id() . ':' . $option, 'site-options' ); }
        return get_site_transient( $name );
    }
    public static function get( string $provider, string $path = '', array $params = array(), int $ttl = 3600 ): array {
        if ( ! current_user_can( 'msc_create_global' ) && ! current_user_can( 'msc_edit_global' ) ) { throw new SourceError( 'Global editing permission is required.', 403 ); }
        if ( ! isset( self::ROOTS[ $provider ] ) || ! preg_match( '~^[a-zA-Z0-9/]*$~D', $path ) || 'tvmaze' !== $provider && '' !== $path ) { throw new SourceError( 'Unsupported data source.' ); }
        if ( 'tvmaze' !== $provider ) { $params += array( 'format' => 'json', 'formatversion' => 2, 'maxlag' => 5 ); }
        $url = add_query_arg( $params, self::ROOTS[ $provider ] . $path );
        $key = 'msc_open_' . hash( 'sha256', $url ); $cached = get_site_transient( $key );
        if ( is_array( $cached ) ) { return $cached; }
        Database::lock( 'open:rate:' . $provider, static function () use ( $provider ) {
            $until = (int) self::fresh( 'msc_open_backoff_' . $provider );
            if ( $until > time() ) { throw new SourceError( 'The source requested a pause. Retry in ' . ( $until - time() ) . ' seconds; saved content is unchanged.', 429 ); }
            $name = 'msc_open_rate_' . $provider; $rate = self::fresh( $name );
            if ( ! is_array( $rate ) || time() - $rate['start'] >= 10 ) { $rate = array( 'start' => time(), 'count' => 0 ); }
            if ( $rate['count'] >= 12 ) { throw new SourceError( 'Please wait a few seconds before requesting more source data.', 429 ); }
            ++$rate['count']; set_site_transient( $name, $rate, 10 );
        } );
        $response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 5 * MB_IN_BYTES, 'sslverify' => true, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'MultisiteSharedContent/' . MSC_VERSION . ' (' . home_url( '/' ) . '; https://github.com/TerminalDogma/Wordpress-MCP)' ) ) );
        if ( is_wp_error( $response ) ) { throw new SourceError( 'Could not reach ' . $provider . '. Check your connection and retry; saved content is unchanged.', 502 ); }
        $code = wp_remote_retrieve_response_code( $response );
        if ( 429 === $code || 503 === $code ) {
            $delay = max( 10, min( 300, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ) );
            set_site_transient( 'msc_open_backoff_' . $provider, time() + $delay, $delay );
            throw new SourceError( 'The source is busy. Retry in ' . $delay . ' seconds.', 429 );
        }
        if ( 404 === $code ) { throw new SourceError( 'This source record was not found.', 404 ); }
        if ( 200 !== $code ) { throw new SourceError( 'The source is temporarily unavailable. Retry later.', 502 ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true, 64 );
        if ( ! is_array( $data ) ) { throw new SourceError( 'The source returned an unreadable response.', 502 ); }
        if ( isset( $data['error'] ) ) {
            if ( 'maxlag' === ( $data['error']['code'] ?? '' ) ) { set_site_transient( 'msc_open_backoff_' . $provider, time() + 15, 15 ); throw new SourceError( 'Wikimedia is catching up. Retry in 15 seconds.', 429 ); }
            throw new SourceError( 'The source could not complete this request.', 502 );
        }
        set_site_transient( $key, $data, $ttl ); return $data;
    }
    public static function numeric( string $id ): string {
        if ( ! preg_match( '/^[1-9][0-9]{0,9}$/D', $id ) ) { throw new SourceError( 'Choose a valid TVmaze record.' ); } return $id;
    }
    public static function qid( string $id ): string {
        if ( ! preg_match( '/^Q[1-9][0-9]{0,11}$/D', $id ) ) { throw new SourceError( 'Choose a valid Wikidata item.' ); } return $id;
    }
    public static function entities( array $ids ): array {
        if ( ! current_user_can( 'msc_create_global' ) && ! current_user_can( 'msc_edit_global' ) ) { throw new SourceError( 'Global editing permission is required.', 403 ); }
        $result = array(); $ids = array_values( array_unique( array_filter( $ids ) ) );
        foreach ( $ids as $id ) { self::qid( $id ); }
        $missing = array();
        foreach ( $ids as $id ) { $cached = get_site_transient( 'msc_open_entity_' . $id ); if ( is_array( $cached ) ) { $result[ $id ] = $cached; } else { $missing[] = $id; } }
        // Famous people's claim sets can be large. Keep batches below the response-size bound.
        foreach ( array_chunk( $missing, 10 ) as $chunk ) {
            $response = self::get( 'wikidata', '', array( 'action' => 'wbgetentities', 'ids' => implode( '|', $chunk ), 'props' => 'labels|descriptions|claims|sitelinks', 'languages' => 'en', 'sitefilter' => 'enwiki' ), DAY_IN_SECONDS );
            foreach ( (array) ( $response['entities'] ?? array() ) as $entity ) { if ( isset( $entity['id'] ) && in_array( $entity['id'], $chunk, true ) && ! isset( $entity['missing'] ) ) { $result[ $entity['id'] ] = $entity; set_site_transient( 'msc_open_entity_' . $entity['id'], $entity, DAY_IN_SECONDS ); } }
        }
        return $result;
    }
}
