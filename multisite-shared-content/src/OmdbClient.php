<?php
namespace MSC;

/** OMDb requests stay on the server. API keys never enter forms, caches or errors. */
final class OmdbClient {
	public const ENDPOINT = 'https://www.omdbapi.com/';
	public static function key(): string {
		return trim( (string) ( defined( 'MSC_OMDB_API_KEY' ) ? MSC_OMDB_API_KEY : ( getenv( 'OMDB_API_KEY' ) ?: get_network_option( get_current_network_id(), 'msc_omdb_api_key', '' ) ) ) );
	}
	public static function configured_by_server(): bool { return defined( 'MSC_OMDB_API_KEY' ) || (bool) getenv( 'OMDB_API_KEY' ); }
	public static function set_key( string $key, bool $clear = false ): void {
		Permissions::require( 'manage_network_options' );
		if ( self::configured_by_server() ) { throw new OmdbError( 'The OMDb key is controlled by server configuration.' ); }
		$key = trim( $key );
		if ( $clear ) { delete_network_option( get_current_network_id(), 'msc_omdb_api_key' ); return; }
		if ( '' === $key ) { return; }
		if ( ! preg_match( '/^[A-Za-z0-9_-]{4,128}$/D', $key ) ) { throw new OmdbError( 'Enter a valid OMDb API key.' ); }
		if ( ! update_network_option( get_current_network_id(), 'msc_omdb_api_key', $key ) && get_network_option( get_current_network_id(), 'msc_omdb_api_key', '' ) !== $key ) { throw new OmdbError( 'The API key could not be saved. Please try again.', 503 ); }
	}
	public static function imdb( string $id ): string {
		if ( ! preg_match( '/^tt[0-9]{7,12}$/D', $id ) ) { throw new OmdbError( 'Choose a valid IMDb title ID.' ); }
		return $id;
	}
	public static function poster_url( $url ): string {
		if ( ! is_string( $url ) || strlen( $url ) > 2048 ) { return ''; }
		$p = wp_parse_url( $url );
		return $p && 'https' === ( $p['scheme'] ?? '' ) && in_array( strtolower( $p['host'] ?? '' ), array( 'm.media-amazon.com', 'ia.media-imdb.com', 'images-na.ssl-images-amazon.com', 'images.media-imdb.com' ), true ) && empty( $p['user'] ) && empty( $p['pass'] ) && empty( $p['port'] ) && empty( $p['query'] ) && empty( $p['fragment'] ) ? esc_url_raw( $url ) : '';
	}
	public static function fresh_transient( string $name ) {
		wp_cache_delete( $name, 'site-transient' );
		foreach ( array( '_site_transient_' . $name, '_site_transient_timeout_' . $name ) as $option ) { wp_cache_delete( get_current_network_id() . ':' . $option, 'site-options' ); }
		return get_site_transient( $name );
	}
	private static function budget(): void {
		$name = 'msc_omdb_rate_' . get_current_user_id();
		Database::lock( $name, static function () use ( $name ) {
			$rate = self::fresh_transient( $name );
			if ( ! is_array( $rate ) || time() - $rate['start'] >= 60 ) { $rate = array( 'start' => time(), 'count' => 0 ); }
			if ( $rate['count'] >= 30 ) { throw new OmdbError( 'OMDb searches are limited to 30 new requests per minute per user. Try again shortly.', 429 ); }
			++$rate['count']; set_site_transient( $name, $rate, 60 );
		} );
	}
	private static function request( array $params, int $ttl ): array {
		if ( ! current_user_can( 'msc_create_global' ) && ! current_user_can( 'msc_edit_global' ) ) { throw new OmdbError( 'Global editing permission is required to use OMDb.', 403 ); }
		$key = self::key(); if ( ! $key ) { throw new OmdbError( 'Set the OMDb API key under Network Admin → Shared Content → OMDb API.', 503 ); }
		$cache = 'msc_omdb_' . hash( 'sha256', $key . ':' . wp_json_encode( $params ) );
		$cached = get_site_transient( $cache ); if ( is_array( $cached ) ) { return $cached; }
		self::budget();
		$response = wp_remote_get( add_query_arg( array_merge( $params, array( 'apikey' => $key, 'r' => 'json' ) ), self::ENDPOINT ), array( 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 2 * MB_IN_BYTES, 'sslverify' => true ) );
		if ( is_wp_error( $response ) ) { throw new OmdbError( 'Could not reach OMDb. Please try again.', 502 ); }
		$code = wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) { throw new OmdbError( 'OMDb’s request limit has been reached. Try again later.', 429 ); }
		if ( in_array( $code, array( 401, 403 ), true ) ) { throw new OmdbError( 'OMDb rejected the API key. Check its value and activation.', 503 ); }
		if ( 200 !== $code ) { throw new OmdbError( 'OMDb is temporarily unavailable. Please try again.', 502 ); }
		$body = str_replace( $key, '[redacted]', wp_remote_retrieve_body( $response ) );
		$data = json_decode( $body, true, 32 );
		if ( ! is_array( $data ) || ! isset( $data['Response'] ) ) { throw new OmdbError( 'OMDb returned an unreadable response.', 502 ); }
		array_walk_recursive( $data, static function ( &$value ) use ( $key ) { if ( is_string( $value ) ) { $value = str_replace( $key, '[redacted]', $value ); } } );
		if ( 'True' !== $data['Response'] ) {
			$error = strtolower( is_string( $data['Error'] ?? null ) ? $data['Error'] : '' );
			if ( str_contains( $error, 'limit' ) ) { throw new OmdbError( 'OMDb’s daily request limit has been reached. Try again later.', 429 ); }
			if ( str_contains( $error, 'api key' ) || str_contains( $error, 'apikey' ) || str_contains( $error, 'activate' ) ) { throw new OmdbError( 'OMDb rejected the API key. Check its value and activation.', 503 ); }
			if ( str_contains( $error, 'not found' ) || str_contains( $error, 'incorrect imdb' ) ) { throw new OmdbError( 'No matching OMDb title was found.', 404 ); }
			if ( str_contains( $error, 'too many' ) ) { throw new OmdbError( 'OMDb found too many titles. Add more of the title or a release year.' ); }
			throw new OmdbError( 'OMDb could not complete this request. Check the title and filters.', 502 );
		}
		// Whitelist and sanitize upstream data before caching or showing it.
		$clean = array();
		foreach ( array( 'Title', 'Year', 'Rated', 'Released', 'Runtime', 'Genre', 'Director', 'Writer', 'Actors', 'Plot', 'Language', 'Country', 'Awards', 'imdbRating', 'imdbVotes', 'imdbID', 'Type', 'DVD', 'BoxOffice', 'Production', 'Website', 'totalSeasons', 'Season', 'Episode', 'seriesID', 'Metascore', 'totalResults' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_scalar( $data[ $field ] ) ) { $clean[ $field ] = 'Plot' === $field ? sanitize_textarea_field( $data[ $field ] ) : sanitize_text_field( $data[ $field ] ); }
		}
		$clean['Poster'] = self::poster_url( $data['Poster'] ?? '' );
		if ( isset( $data['Search'] ) && is_array( $data['Search'] ) ) {
			$clean['Search'] = array();
			foreach ( array_slice( $data['Search'], 0, 10 ) as $item ) {
				if ( ! is_array( $item ) || ! is_string( $item['imdbID'] ?? null ) || ! is_string( $item['Type'] ?? null ) || ! preg_match( '/^tt[0-9]{7,12}$/D', $item['imdbID'] ?? '' ) || ! in_array( $item['Type'] ?? '', array( 'movie', 'series', 'episode' ), true ) ) { continue; }
				$clean['Search'][] = array( 'Title' => sanitize_text_field( $item['Title'] ?? '' ), 'Year' => sanitize_text_field( $item['Year'] ?? '' ), 'imdbID' => $item['imdbID'], 'Type' => $item['Type'], 'Poster' => self::poster_url( $item['Poster'] ?? '' ) );
			}
		}
		$clean['Ratings'] = array(); foreach ( array_slice( is_array( $data['Ratings'] ?? null ) ? $data['Ratings'] : array(), 0, 20 ) as $rating ) { if ( is_array( $rating ) ) { $clean['Ratings'][] = array( 'Source' => sanitize_text_field( $rating['Source'] ?? '' ), 'Value' => sanitize_text_field( $rating['Value'] ?? '' ) ); } }
		set_site_transient( $cache, $clean, $ttl ); return $clean;
	}
	public static function search( string $title, string $kind = '', string $year = '', int $page = 1 ): array {
		$title = trim( sanitize_text_field( $title ) );
		if ( '' === $title || strlen( $title ) > 200 || ! in_array( $kind, array( '', 'movie', 'series', 'episode' ), true ) || $year && ! preg_match( '/^[0-9]{4}$/D', $year ) || $page < 1 || $page > 100 ) { throw new OmdbError( 'Enter a title, an optional four-digit year, and a valid result type/page.' ); }
		$params = array( 's' => $title, 'page' => $page ); if ( $kind ) { $params['type'] = $kind; } if ( $year ) { $params['y'] = $year; }
		try { $data = self::request( $params, 15 * MINUTE_IN_SECONDS ); }
		catch ( OmdbError $e ) { if ( 404 !== $e->status ) { throw $e; } return array( 'Search' => array(), 'totalResults' => 0 ); }
		if ( ! isset( $data['Search'] ) ) { throw new OmdbError( 'OMDb returned no search list.', 502 ); }
		return array( 'Search' => $data['Search'], 'totalResults' => max( 0, (int) ( $data['totalResults'] ?? 0 ) ) );
	}
	public static function details( string $imdb ): array {
		$data = self::request( array( 'i' => self::imdb( $imdb ), 'plot' => 'full' ), 6 * HOUR_IN_SECONDS );
		if ( ( $data['imdbID'] ?? '' ) !== $imdb || '' === ( $data['Title'] ?? '' ) || ! in_array( $data['Type'] ?? '', array( 'movie', 'series', 'episode' ), true ) ) { throw new OmdbError( 'OMDb returned a different or unsupported title.', 502 ); }
		return $data;
	}
}
