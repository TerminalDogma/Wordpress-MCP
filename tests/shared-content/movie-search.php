<?php
use MSC\{OpenClient,OpenSources,SourceError};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local network required.' ); }
define( 'MSC_MOVIE_SEARCH_FIXTURES', true ); require __DIR__ . '/movie-search-fixtures.php';
$GLOBALS['movie_search_checks'] = 0;
function ms_check( $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['movie_search_checks']; WP_CLI::log( 'PASS: ' . $message ); }
function ms_reject( callable $fn, string $message, int $status ): void { try { $fn(); } catch ( SourceError $e ) { ms_check( $status === $e->status, $message ); return; } throw new RuntimeException( 'FAIL: expected rejection: ' . $message ); }
function ms_reset(): void { delete_site_transient( 'msc_open_rate_wikidata' ); delete_site_transient( 'msc_open_backoff_wikidata' ); }
wp_set_current_user( 1 ); $old_seed = get_network_option( get_current_network_id(), 'msc_movie_search_fixture_seed', false ); $seed = random_int( 930000000, 950000000 ); update_network_option( get_current_network_id(), 'msc_movie_search_fixture_seed', $seed );
try {
    ms_reset(); $before = MSC_Movie_Search_Fixtures::$requests; $result = OpenSources::search( 'msc_movie', 'Movie batch ' . $seed );
    ms_check( 12 === count( $result['items'] ) && 12 === $result['next'] && 'Q' . $seed === $result['items'][0]['id'], 'A cold twelve-result search returns every film in provider order without tripping its own limit' );
    ms_check( 4 === MSC_Movie_Search_Fixtures::$requests - $before, 'Twelve distinct film classes require four total requests rather than fifteen' );
    $calls = MSC_Movie_Search_Fixtures::$calls; $class_call = end( $calls ); ms_check( 'claims' === $class_call['props'] && 12 === count( explode( '|', $class_call['ids'] ) ), 'Class validation requests only claims and batches all result classes' );
    $before = MSC_Movie_Search_Fixtures::$requests; ms_check( $result === OpenSources::search( 'msc_movie', 'Movie batch ' . $seed ) && $before === MSC_Movie_Search_Fixtures::$requests, 'Repeated searches use shared response/entity caches without spending rate slots' );
    $class = OpenClient::entities( array( 'Q' . ( $seed + 100 ) ) ); ms_check( isset( $class[ 'Q' . ( $seed + 100 ) ]['labels'] ), 'Claims-only cache entries cannot masquerade as full entities during a later preview' );
    ms_reset(); $before = MSC_Movie_Search_Fixtures::$requests; $deep = OpenSources::search( 'msc_movie', 'Movie deep ' . $seed ); ms_check( 12 === count( $deep['items'] ) && 5 === MSC_Movie_Search_Fixtures::$requests - $before, 'Deeper subclass checks batch each graph level across all candidates' );
    ms_reset(); $mixed = OpenSources::search( 'msc_movie', 'Movie mixed ' . $seed ); ms_check( 9 === count( $mixed['items'] ), 'Humans, cyclic classes and missing classes remain excluded from Movie results' );
    ms_reset(); $none = OpenSources::search( 'msc_movie', 'Movie missing ' . $seed ); ms_check( ! $none['items'], 'Explicitly missing classes produce an honest empty result rather than a quota error' );
    $before = MSC_Movie_Search_Fixtures::$requests; OpenClient::entities( array( 'Q' . ( $seed + 3999 ) ) ); ms_check( $before === MSC_Movie_Search_Fixtures::$requests, 'Missing entity IDs are shared between lightweight and full lookups' );
    ms_reset(); set_site_transient( 'msc_open_rate_wikidata', array( 'start' => time() - 9, 'count' => 12 ), 10 ); $before = MSC_Movie_Search_Fixtures::$requests;
    $resumed = OpenClient::paced( static fn() => OpenClient::entities( array( 'Q' . ( $seed + 105 ) ) ) ); ms_check( isset( $resumed[ 'Q' . ( $seed + 105 ) ]['labels'] ) && MSC_Movie_Search_Fixtures::$requests === $before + 1, 'An editor action waits for a nearly-expired local window and completes automatically' );
    ms_reset(); set_site_transient( 'msc_open_rate_wikidata', array( 'start' => time() + 600, 'count' => 12 ), 610 ); OpenClient::entities( array( 'Q' . ( $seed + 106 ) ) ); ms_check( OpenClient::fresh( 'msc_open_rate_wikidata' )['count'] === 1, 'A future or malformed local window cannot leave the importer permanently rate limited' );
    ms_reset(); set_site_transient( 'msc_open_rate_wikidata', array( 'start' => time(), 'count' => 12 ), 10 );
    ms_reject( static fn() => OpenClient::paced( static fn() => OpenClient::paced( static fn() => OpenClient::entities( array( 'Q' . ( $seed + 107 ) ) ), 20 ), 0 ), 'Nested actions cannot reset or exceed the bounded wait budget', 429 );
    ms_reject( static fn() => OpenClient::entities( array( 'Q' . ( $seed + 107 ) ) ), 'Standalone requests retain the shared request cap after a paced action exits', 429 );
    ms_reset(); ms_reject( static fn() => OpenSources::search( 'msc_movie', 'Movie quota ' . $seed ), 'Genuine source 429 responses still enforce a provider cooldown', 429 ); $before = MSC_Movie_Search_Fixtures::$requests;
    ms_reject( static fn() => OpenClient::paced( static fn() => OpenClient::entities( array( 'Q' . ( $seed + 108 ) ) ) ), 'Automatic local pacing cannot bypass an upstream Retry-After pause', 429 ); ms_check( $before === MSC_Movie_Search_Fixtures::$requests, 'Provider cooldowns trigger no extra HTTP requests' );
    ms_check( $result === OpenSources::search( 'msc_movie', 'Movie batch ' . $seed ), 'Cached searches remain available during upstream cooldowns' );
    wp_set_current_user( 0 ); ms_reject( static fn() => OpenSources::search( 'msc_movie', 'Movie batch ' . $seed ), 'Cached searches still require global editing permission', 403 );
} finally { wp_set_current_user( 1 ); ms_reset(); if ( false === $old_seed ) { delete_network_option( get_current_network_id(), 'msc_movie_search_fixture_seed' ); } else { update_network_option( get_current_network_id(), 'msc_movie_search_fixture_seed', $old_seed ); } }
WP_CLI::success( $GLOBALS['movie_search_checks'] . ' cold Movie search, batching, pacing, cache and cooldown checks passed.' );
