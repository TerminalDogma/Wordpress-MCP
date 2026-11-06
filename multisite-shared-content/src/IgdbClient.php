<?php
namespace MSC;

/** IGDB v4: server-side Twitch client credentials, caching and bounded requests. */
final class IgdbClient {
    public static function credentials(): array {
        $id = defined( 'MSC_IGDB_CLIENT_ID' ) ? (string) MSC_IGDB_CLIENT_ID : (string) getenv( 'IGDB_CLIENT_ID' );
        $secret = defined( 'MSC_IGDB_CLIENT_SECRET' ) ? (string) MSC_IGDB_CLIENT_SECRET : (string) getenv( 'IGDB_CLIENT_SECRET' );
        return $id || $secret ? array( $id, $secret ) : (array) get_network_option( get_current_network_id(), 'msc_igdb_credentials', array( '', '' ) );
    }
    public static function configured(): bool { [ $id, $secret ] = self::credentials(); return '' !== $id && '' !== $secret; }
    public static function managed(): bool { return defined( 'MSC_IGDB_CLIENT_ID' ) || defined( 'MSC_IGDB_CLIENT_SECRET' ) || getenv( 'IGDB_CLIENT_ID' ) || getenv( 'IGDB_CLIENT_SECRET' ); }
    public static function save( string $id, string $secret, bool $clear = false ): void {
        if ( ! current_user_can( 'manage_network_options' ) || self::managed() ) { throw new SourceError( 'Network permission is required; server-managed credentials cannot be changed here.', 403 ); }
        if ( $clear ) { delete_network_option( get_current_network_id(), 'msc_igdb_credentials' ); return; }
        $old = self::credentials(); $id = trim( $id ); $secret = trim( $secret ); if ( '' === $id ) { $id = $old[0]; } if ( '' === $secret ) { $secret = $old[1]; }
        if ( ! preg_match( '/^[A-Za-z0-9_-]{5,200}$/D', $id ) || ! preg_match( '/^[A-Za-z0-9_-]{5,200}$/D', $secret ) ) { throw new SourceError( 'Enter a valid Twitch Client ID and Client Secret.' ); }
        update_network_option( get_current_network_id(), 'msc_igdb_credentials', array( $id, $secret ) );
    }
    private static function fingerprint(): string { return hash( 'sha256', wp_json_encode( self::credentials() ) ); }
    private static function token( bool $fresh = false ): string {
        if ( ! self::configured() ) { throw new SourceError( 'Configure IGDB under Network Admin → Shared Content → IGDB API before searching.', 503 ); }
        $key = 'msc_igdb_token_' . self::fingerprint(); $cached = get_site_transient( $key ); if ( ! $fresh && is_string( $cached ) && $cached ) { return $cached; }
        [ $id, $secret ] = self::credentials();
        $response = wp_safe_remote_post( 'https://id.twitch.tv/oauth2/token', array( 'timeout' => 15, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 64000, 'body' => array( 'client_id' => $id, 'client_secret' => $secret, 'grant_type' => 'client_credentials' ) ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { throw new SourceError( 'Twitch authentication failed. Check the IGDB Client ID and Client Secret; credentials were not displayed or sent to the browser.', 503 ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true ); $token = $data['access_token'] ?? '';
        if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9._-]{5,1000}$/D', $token ) || (int) ( $data['expires_in'] ?? 0 ) < 120 ) { throw new SourceError( 'Twitch returned an invalid authentication response.', 502 ); }
        set_site_transient( $key, $token, min( 60 * DAY_IN_SECONDS, (int) $data['expires_in'] - 60 ) ); return $token;
    }
    public static function request( string $endpoint, string $query ): array {
        if ( ! OpenImport::allowed() || ! Schema::enabled( 'msc_game' ) ) { throw new SourceError( 'Global import permission and Video Games enabled on this site are required.', 403 ); }
        if ( ! in_array( $endpoint, array( 'games', 'platforms' ), true ) ) { throw new SourceError( 'Unsupported IGDB endpoint.' ); }
        $key = 'msc_igdb_response_' . hash( 'sha256', self::fingerprint() . $endpoint . $query ); $cached = get_site_transient( $key ); if ( is_array( $cached ) ) { return $cached; }
        if ( get_site_transient( 'msc_igdb_backoff' ) ) { throw new SourceError( 'IGDB is rate limited. Wait briefly, then retry.', 429 ); }
        $token = self::token(); [ $id ] = self::credentials();
        for ( $attempt = 0; $attempt < 2; ++$attempt ) {
            Database::lock( 'igdb:rate', static function () {
                $state = (array) get_site_transient( 'msc_igdb_rate' ); $second = (int) floor( microtime( true ) ); $count = ( $state['second'] ?? 0 ) === $second ? (int) $state['count'] : 0;
                if ( $count >= 4 ) { throw new SourceError( 'IGDB allows four requests per second. Wait briefly, then retry.', 429 ); } set_site_transient( 'msc_igdb_rate', array( 'second' => $second, 'count' => $count + 1 ), 2 );
            } );
            $response = wp_safe_remote_post( 'https://api.igdb.com/v4/' . $endpoint, array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 2 * MB_IN_BYTES, 'headers' => array( 'Client-ID' => $id, 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'text/plain' ), 'body' => $query ) );
            if ( is_wp_error( $response ) ) { throw new SourceError( 'IGDB could not be reached. Saved content has not changed.', 502 ); }
            $status = wp_remote_retrieve_response_code( $response ); if ( 401 === $status && 0 === $attempt ) { $token = self::token( true ); continue; }
            if ( 429 === $status ) { $delay = max( 1, min( 300, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ?: 2 ) ); set_site_transient( 'msc_igdb_backoff', true, $delay ); throw new SourceError( 'IGDB requested a retry after ' . $delay . ' seconds.', 429 ); }
            if ( 200 !== $status ) { throw new SourceError( 'IGDB rejected this request. Check API access or try a different search.', 502 ); }
            $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( ! is_array( $data ) || ! array_is_list( $data ) ) { throw new SourceError( 'IGDB returned unreadable game data.', 502 ); }
            set_site_transient( $key, $data, 15 * MINUTE_IN_SECONDS ); return $data;
        } throw new SourceError( 'IGDB authentication expired. Check credentials and retry.', 503 );
    }
}
