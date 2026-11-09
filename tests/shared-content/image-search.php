<?php
use MSC\{ImageSearch, ImageSearchAdmin, SourceError};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local network required.' );
}
define( 'MSC_IMAGE_TEST_FIXTURES', true );
require __DIR__ . '/image-fixtures.php';
$GLOBALS['search_checks'] = 0;
function search_check( $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['search_checks'];
	WP_CLI::log( 'PASS: ' . $message );
}
function search_reject( callable $fn, string $message, int $status = 0 ): void {
	try {
		$fn();
	} catch ( Throwable $e ) {
		search_check( ! $status || $e instanceof SourceError && $e->status === $status, $message );
		return;
	}
	throw new RuntimeException( 'FAIL: expected search rejection: ' . $message );
}
wp_set_current_user( 1 );
$old_key = get_network_option( get_current_network_id(), 'msc_image_search_key', false );
$old_env = getenv( 'SERPER_API_KEY' );
putenv( 'SERPER_API_KEY' );
$key = 'fixture-search-' . wp_generate_uuid4();
try {
	ImageSearch::save_key( $key );
	ImageSearch::save_key( '' );
	search_check( $key === ImageSearch::key(), 'Blank settings submissions preserve the private key' );
	ob_start();
	ImageSearchAdmin::page();
	$html = ob_get_clean();
	search_check( ! str_contains( $html, $key ) && str_contains( $html, 'Status: Configured' ), 'The settings form never prints the saved key' );
	putenv( 'SERPER_API_KEY=server-managed-search-key' );
	search_check( 'server-managed-search-key' === ImageSearch::key(), 'Server configuration takes priority' );
	search_reject( static fn() => ImageSearch::save_key( 'a-new-key-that-must-not-save' ), 'Server-managed keys cannot be changed in the form', 403 );
	putenv( 'SERPER_API_KEY' );
	$query  = 'Quoted "Movie" ' . wp_generate_uuid4();
	$before = MSC_Image_Fixtures::$searches;
	$r      = ImageSearch::search( $query );
	$same   = ImageSearch::search( $query );
	search_check( count( $r['items'] ) === 10 && 2 === $r['next_page'] && $same === $r && $before + 1 === MSC_Image_Fixtures::$searches, 'Google image results and paging are cached per key and query' );
	$request = MSC_Image_Fixtures::$last_search;
	$body    = json_decode( $request['body'], true );
	search_check( $key === $request['headers']['X-API-KEY'] && ! str_contains( $request['body'], $key ) && $query === $body['q'] && 10 === $body['num'], 'The key stays in server-side headers and query JSON is correctly escaped' );
	search_check( true === $request['sslverify'] && 0 === $request['redirection'] && MB_IN_BYTES === $request['limit_response_size'], 'Provider requests verify TLS and bound response size and redirects' );
	ImageSearch::save_key( $key . '-rotated' );
	$before = MSC_Image_Fixtures::$searches;
	ImageSearch::search( $query );
	search_check( $before + 1 === MSC_Image_Fixtures::$searches, 'A changed provider key cannot reuse another key\'s cached result' );
	ImageSearch::save_key( $key );
	$second = ImageSearch::search( $query, 2 );
	search_check( 2 === count( $second['items'] ) && null === $second['next_page'] && 'Poster result 11' === $second['items'][0]['title'], 'Additional result pages retain original image and source links' );
	search_check( ! ImageSearch::search( 'empty ' . wp_generate_uuid4() )['items'], 'An empty image search is a normal result' );
	foreach ( array( '', str_repeat( 'x', 161 ) ) as $q ) {
		search_reject( static fn() => ImageSearch::search( $q ), 'Empty and overlong queries are rejected', 400 );
	}
	search_reject( static fn() => ImageSearch::search( 'poster', 11 ), 'Out-of-range pages are rejected', 400 );
	$items = ImageSearch::normalize(
		array(
			array( 'imageUrl' => 'javascript:alert(1)' ),
			array( 'imageUrl' => 'https://127.0.0.1/a.png' ),
			array(
				'imageUrl'     => 'https://93.184.216.34/image.png',
				'thumbnailUrl' => 'data:image/png;base64,AAA',
				'title'        => '<script>Bad</script>Title',
				'link'         => 'http://93.184.216.34/source',
			),
			array( 'imageUrl' => 'https://93.184.216.34/image.png' ),
		)
	);
	search_check( 1 === count( $items ) && '' === $items[0]['thumbnail'] && ! str_contains( $items[0]['title'], '<' ) && str_starts_with( $items[0]['source_url'], 'http://' ), 'Results reject unsafe links, deduplicate images and sanitize text' );
	foreach ( array(
		'badkey'    => 503,
		'malformed' => 502,
		'offline'   => 502,
	) as $q => $status ) {
		search_reject( static fn() => ImageSearch::search( $q . ' ' . wp_generate_uuid4() ), 'Safe provider error handling for ' . $q, $status );
	}
	search_reject( static fn() => ImageSearch::search( 'quota ' . wp_generate_uuid4() ), 'Provider quotas produce an actionable retry message', 429 );
	$before = MSC_Image_Fixtures::$searches;
	search_reject( static fn() => ImageSearch::search( 'cooldown ' . wp_generate_uuid4() ), 'Provider backoff is shared across requests', 429 );
	search_check( $before === MSC_Image_Fixtures::$searches && $r === ImageSearch::search( $query ), 'Backoff avoids upstream requests while cached results remain usable' );
	delete_site_transient( 'msc_image_search_backoff_' . hash( 'sha256', $key ) );
	set_site_transient(
		'msc_image_search_rate_' . get_current_user_id(),
		array(
			'minute' => (int) floor( time() / 60 ),
			'count'  => 30,
		),
		65
	);
	search_reject( static fn() => ImageSearch::search( 'new budget ' . wp_generate_uuid4() ), 'A per-user budget limits uncached queries', 429 );
	search_check( $r === ImageSearch::search( $query ), 'The user budget does not block cached results' );
	delete_site_transient( 'msc_image_search_rate_' . get_current_user_id() );
	wp_set_current_user( 0 );
	search_reject( static fn() => ImageSearch::search( $query ), 'Anonymous users cannot read cached image search results', 403 );
	wp_set_current_user( 1 );
	ImageSearch::save_key( '', true );
	search_reject( static fn() => ImageSearch::search( $query ), 'A missing key gives a configuration message', 503 );
} finally {
	wp_set_current_user( 1 );
	delete_site_transient( 'msc_image_search_rate_' . get_current_user_id() );
	delete_site_transient( 'msc_image_search_backoff_' . hash( 'sha256', $key ) );
	if ( false === $old_key ) {
		delete_network_option( get_current_network_id(), 'msc_image_search_key' );
	} else {
		update_network_option( get_current_network_id(), 'msc_image_search_key', $old_key );
	}
	if ( false === $old_env ) {
		putenv( 'SERPER_API_KEY' );
	} else {
		putenv( 'SERPER_API_KEY=' . $old_env );
	}
}
WP_CLI::success( $GLOBALS['search_checks'] . ' embedded Google image search, credentials, cache, quota and result validation checks passed.' );
