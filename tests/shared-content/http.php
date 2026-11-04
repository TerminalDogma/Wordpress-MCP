<?php
/** Requests traverse Apache and WordPress. No authorization is bypassed. */
function http_msc( string $path, array $headers = array(), string $method = 'GET', ?array $data = null ): array {
	$ch = curl_init( rtrim( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', '/' ) . $path );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CONNECT_TO => array( ( wp_parse_url( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', PHP_URL_HOST ) ) . ':' . ( wp_parse_url( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', PHP_URL_PORT ) ?: 80 ) . ':wordpress:80' ), CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20 ) );
	if ( $data ) { $headers[] = 'Content-Type: application/json'; curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $data ) ); }
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method ); curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
	$raw = curl_exec( $ch );
	if ( false === $raw ) { throw new RuntimeException( curl_error( $ch ) ); }
	$code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); $size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE ); curl_close( $ch );
	return array( $code, substr( $raw, 0, $size ), substr( $raw, $size ) );
}
function parallel_msc( string $path, array $bodies, array $headers ): array {
	$multi = curl_multi_init(); $handles = array();
	foreach ( $bodies as $data ) {
		$ch = curl_init( rtrim( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', '/' ) . $path );
		curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECT_TO => array( ( wp_parse_url( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', PHP_URL_HOST ) ) . ':' . ( wp_parse_url( getenv( 'WORDPRESS_URL' ) ?: 'http://localhost:8080', PHP_URL_PORT ) ?: 80 ) . ':wordpress:80' ), CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => wp_json_encode( $data ), CURLOPT_HTTPHEADER => array_merge( $headers, array( 'Content-Type: application/json' ) ) ) );
		curl_multi_add_handle( $multi, $ch ); $handles[] = $ch;
	}
	do { $status = curl_multi_exec( $multi, $running ); if ( $running ) { curl_multi_select( $multi, 1 ); } } while ( $running && CURLM_OK === $status );
	$codes = array(); foreach ( $handles as $ch ) { $codes[] = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); curl_multi_remove_handle( $multi, $ch ); curl_close( $ch ); }
	curl_multi_close( $multi ); sort( $codes ); return $codes;
}
// Proxy-free request targets only the local Docker bridge service, not an Internet destination.
wp_set_current_user( 1 );
switch_to_blog( 2 );
try {
	$r = \MSC\Plugin::store()->create( 'msc_show', array( 'title' => 'HTTP Smoke ' . wp_generate_uuid4(), 'excerpt' => 'Functional HTTP record', 'content' => '<p>Functional HTTP record</p>', 'seo_description' => 'MSC HTTP description' ) );
	\MSC\Plugin::materializer()->sync( (int) $r['id'], 2 ); $m = \MSC\Plugin::store()->mapping( (int) $r['id'] );
	\MSC\Plugin::store()->local( $r['id'], array(), array(), (int) $m['local_version'], 'publish' );
	$url = get_permalink( msc_local_post_id( $r['id'] ) ); $parts = wp_parse_url( $url ); $path = $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	[$code,$headers,$body] = http_msc( $path );
	if ( 200 !== $code || ! str_contains( strtolower( $headers ), 'x-robots-tag: noindex' ) || ! preg_match( '/<meta[^>]+name=[\'\"]robots[\'\"][^>]+noindex/i', $body ) || ! str_contains( $body, 'Functional HTTP record' ) ) { throw new RuntimeException( 'HTTP singular smoke failed for ' . $url . ': status ' . $code . '; headers ' . $headers ); }
	WP_CLI::log( 'PASS: HTTP published native page renders with noindex header and robots meta' );
	[$code,$headers,$body] = http_msc( '/site-a/?rest_route=/msc/v1/records/' . $r['uuid'] );
	$json = json_decode( $body, true );
	if ( 200 !== $code || ( $json['uuid'] ?? '' ) !== $r['uuid'] || isset( $json['global'] ) ) { throw new RuntimeException( 'Public HTTP REST smoke failed: status ' . $code ); }
	WP_CLI::log( 'PASS: HTTP REST returns published effective data without global internals' );
	[$code] = http_msc( '/site-a/?rest_route=/msc/v1/queue' );
	if ( 401 !== $code ) { throw new RuntimeException( 'Unauthenticated network queue access was not denied.' ); }
	WP_CLI::log( 'PASS: HTTP anonymous network queue access denied' );
	$m = \MSC\Plugin::store()->mapping( (int) $r['id'] ); \MSC\Plugin::store()->local( $r['id'], array(), array(), (int) $m['local_version'], 'draft' );
	[$code] = http_msc( '/site-a/?rest_route=/msc/v1/records/' . $r['uuid'] );
	if ( 401 !== $code ) { throw new RuntimeException( 'Anonymous draft REST data was not denied.' ); }
	WP_CLI::log( 'PASS: HTTP anonymous draft record access denied' );
	$token = WP_Session_Tokens::get_instance( 1 )->create( time() + 300 );
	$cookie = wp_generate_auth_cookie( 1, time() + 300, 'logged_in', $token );
	$auth = wp_generate_auth_cookie( 1, time() + 300, 'auth', $token );
	$cookie_header = 'Cookie: ' . LOGGED_IN_COOKIE . '=' . $cookie . '; ' . AUTH_COOKIE . '=' . $auth;
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$nonce = wp_create_nonce( 'wp_rest' );
	$payload = array( 'type' => 'msc_show', 'data' => array( 'title' => 'Authenticated HTTP create' ) );
	[$code] = http_msc( '/site-a/?rest_route=/msc/v1/records', array( $cookie_header ), 'POST', $payload );
	if ( 401 !== $code ) { throw new RuntimeException( 'Cookie REST write without nonce was not denied.' ); }
	WP_CLI::log( 'PASS: HTTP cookie-authenticated write without REST nonce denied' );
	[$code,,$body] = http_msc( '/site-a/?rest_route=/msc/v1/records', array( $cookie_header, 'X-WP-Nonce: ' . $nonce ), 'POST', $payload );
	if ( 200 !== $code || empty( json_decode( $body, true )['uuid'] ) ) { throw new RuntimeException( 'Valid nonce-authenticated REST create failed: ' . $code ); }
	WP_CLI::log( 'PASS: HTTP cookie and valid REST nonce authorize global creation' );
	[$code,,$body] = http_msc( '/site-a/wp-admin/admin.php?page=msc&record=' . $r['uuid'] . '&mode=global', array( $cookie_header ) );
	if ( 200 !== $code || ! str_contains( $body, 'GLOBAL EDITING' ) || ! str_contains( $body, 'Save global record' ) || ! str_contains( $body, 'Choose image or media' ) ) { throw new RuntimeException( 'Authenticated global editor page failed: ' . $code ); }
	WP_CLI::log( 'PASS: HTTP authenticated global editor form renders' );
	[$code,,$body] = http_msc( '/wp-admin/network/admin.php?page=msc-network', array( $cookie_header ) );
	if ( 200 !== $code || ! str_contains( $body, 'Site participation' ) || ! str_contains( $body, 'Type and field schemas' ) ) { throw new RuntimeException( 'Network settings page failed: ' . $code ); }
	WP_CLI::log( 'PASS: HTTP network participation, schema, and queue settings render' );
	// Two actual Apache workers compete for the same native revision token.
	$m = \MSC\Plugin::store()->mapping( (int) $r['id'] );
	$bodies = array( array( 'title' => 'Concurrent editor A', 'msc_expected_version' => (int) $m['local_version'] ), array( 'title' => 'Concurrent editor B', 'msc_expected_version' => (int) $m['local_version'] ) );
	$codes = parallel_msc( '/site-a/?rest_route=/wp/v2/msc_show/' . $m['post_id'], $bodies, array( $cookie_header, 'X-WP-Nonce: ' . $nonce ) );
	if ( array( 200, 409 ) !== $codes ) { throw new RuntimeException( 'Concurrent native edits did not produce one winner and one conflict: ' . wp_json_encode( $codes ) ); }
	WP_CLI::log( 'PASS: Simultaneous HTTP native edits yield one success and one version conflict' );
	WP_Session_Tokens::get_instance( 1 )->destroy( $token ); unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	WP_CLI::success( '9 HTTP smoke checks passed.' );
} finally { restore_current_blog(); }
