<?php
use MSC\{Plugin, Schema, Queue, GamePolicy, GameFields, NetworkSites, ReleaseCalendar, IgdbClient, IgdbSource, IgdbAdmin, OpenSources, OpenImport, Presentation, SourceError, Rest};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local test network required.' );
}
define( 'MSC_IGDB_TEST_FIXTURES', true );
require __DIR__ . '/igdb-fixtures.php';
function ng_check( $ok, string $label ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$GLOBALS['ng_checks'];
	WP_CLI::log( 'PASS: ' . $label );
}
function ng_at( int $site, callable $fn ) {
	switch_to_blog( $site );
	try {
		Plugin::register_posts();
		return $fn();
	} finally {
		restore_current_blog();
	}
}
function ng_drain(): void {
	for ( $i = 0; $i < 200 && Queue::status()['pending']; ++$i ) {
		$r = Queue::run( 1000 );
		if ( $r['errors'] || ! $r['processed'] ) {
			throw new RuntimeException( 'Game test queue failed.' );
		}
	}
}
function ng_reject( callable $fn, string $label, int $status = 0 ): void {
	try {
		$fn();
	} catch ( Throwable $e ) {
		ng_check( ! $status || $e instanceof SourceError && $e->status === $status, $label );
		return;
	}
	throw new RuntimeException( 'FAIL: expected rejection: ' . $label );
}
function ng_reset(): void {
	delete_site_transient( 'msc_igdb_rate' );
	delete_site_transient( 'msc_igdb_backoff' );
}
function ng_publish( string $uuid ): void {
	$r = Plugin::store()->resolved( $uuid );
	Plugin::store()->local( $uuid, array(), array(), (int) $r['mapping']['local_version'], 'publish' );
}
$GLOBALS['ng_checks'] = 0;
wp_set_current_user( 1 );
$sites = array();
$saved = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = NetworkSites::key( $site->path );
	if ( ! $key ) {
		continue;
	}
	$sites[ $key ]           = (int) $site->blog_id;
	$saved[ $site->blog_id ] = array(
		'types'    => get_blog_option( $site->blog_id, 'msc_types', array() ),
		'policy'   => GamePolicy::mode( (int) $site->blog_id ),
		'children' => get_blog_option( $site->blog_id, 'msc_episodes_as_children', false ),
	);
}
$old_creds = get_network_option( get_current_network_id(), 'msc_igdb_credentials', false );
$old_seed  = get_network_option( get_current_network_id(), 'msc_igdb_fixture_seed', false );
try {
	require __DIR__ . '/network-profiles.php';
	ng_check( 4 === count( $sites ), 'Four presentation sites exist' );
	foreach ( $sites as $key => $id ) {
		$p = NetworkSites::profiles()[ $key ];
		ng_check( $p['types'] === get_blog_option( $id, 'msc_types' ), 'Required content types on Site ' . strtoupper( $key ) );
		ng_check( get_blog_option( $id, 'msc_calendar_page' ) > 0, 'Calendar page on Site ' . strtoupper( $key ) );
	}
	ng_check( GamePolicy::platform_allowed( 38, '', $sites['d'] ) && GamePolicy::platform_allowed( 46, '', $sites['d'] ) && ! GamePolicy::platform_allowed( 6, 'PC (Microsoft Windows)', $sites['d'] ), 'Sony policy includes PSP/Vita and excludes PC' );
	ng_at(
		$sites['c'],
		static function () {
			IgdbClient::save( 'fixture-client', 'fixture-secret' );
			IgdbClient::save( '', '' );
			ob_start();
			IgdbAdmin::page();
			$html = ob_get_clean();
			ng_check( ! str_contains( $html, 'fixture-secret' ) && ! str_contains( $html, 'value="fixture-client"' ), 'Saved IGDB credentials never render in settings HTML' );
		}
	);
	$seed = random_int( 870000000, 879000000 );
	update_network_option( get_current_network_id(), 'msc_igdb_fixture_seed', $seed );
	ng_at(
		$sites['c'],
		static function () use ( &$game, $seed ) {
			ng_reset();
			$before = MSC_Igdb_Fixtures::$calls;
			$result = OpenSources::search( 'msc_game', 'Quoted "Title"; where id > 0; ' . $seed, 'igdb', 0 );
			$again  = OpenSources::search( 'msc_game', 'Quoted "Title"; where id > 0; ' . $seed, 'igdb', 0 );
			ng_check( 1 === count( $result['items'] ) && MSC_Igdb_Fixtures::$calls === $before + 1, 'Game title search is cached with escaped APICalypse input' );
			ng_check( str_contains( MSC_Igdb_Fixtures::$last['body'], '\\"Title\\"' ), 'Quoted search stays within its string literal' );
			ng_check( 'fixture-client' === MSC_Igdb_Fixtures::$last['headers']['Client-ID'] && ! str_contains( MSC_Igdb_Fixtures::$last['body'], 'fixture-secret' ), 'Credentials are server-side headers and OAuth body only' );
			ng_reset();
			$preview = OpenImport::preview( 'igdb', (string) $seed, 'msc_game' );
			ng_check( '2099-01-06' === $preview['fields']['release_date'] && 'year' === $preview['fields']['release_dates'][2]['precision'] && '' === $preview['fields']['release_dates'][2]['date'], 'Current IGDB date formats retain exact regional dates and partial windows' );
			$request = array(
				'token'  => $preview['token'],
				'nonce'  => wp_create_nonce( 'msc_open_import' ),
				'fields' => array_keys( $preview['fields'] ),
				'image'  => '1',
			);
			$game    = OpenImport::import( $request )['record'];
			ng_check( (string) $seed === $game['data']['external_ids']['igdb'] && ! empty( $game['data']['poster']['attachment_id'] ), 'Reviewed IGDB import saves identity, taxonomies and portable cover media' );
			ng_publish( $game['uuid'] );
			ng_reset();
			$preview = OpenImport::preview( 'igdb', (string) $seed, 'msc_game' );
			ng_check( $preview['target'] === $game['uuid'], 'Repeated IGDB identity resolves to the existing shared game' );
			$r = Plugin::store()->resolved( $game['uuid'] );
			Plugin::store()->local( $r['uuid'], array( 'excerpt' => 'Curated local text' ), array(), (int) $r['mapping']['local_version'] );
			$request = array(
				'token'  => $preview['token'],
				'nonce'  => wp_create_nonce( 'msc_open_import' ),
				'fields' => array( 'genres' ),
			);
			OpenImport::import( $request );
			ng_check( 'Curated local text' === Plugin::store()->resolved( $game['uuid'] )['data']['excerpt'], 'Selective refresh preserves local overrides' );
			foreach ( array( '401', '429', 'offline', 'malformed' ) as $mode ) {
				ng_reset();
				MSC_Igdb_Fixtures::$mode = $mode;
				if ( '401' === $mode ) {
						$auth = MSC_Igdb_Fixtures::$auth;
						IgdbClient::request( 'games', 'fields id; limit 1; // refresh-' . wp_generate_uuid4() );
						ng_check( MSC_Igdb_Fixtures::$auth === $auth + 1, '401 refreshes the Twitch token once' );
				} else {
					ng_reject( static fn() => IgdbClient::request( 'games', 'fields id; // ' . wp_generate_uuid4() ), 'Safe API failure for ' . $mode, '429' === $mode ? 429 : 502 );
					if ( '429' === $mode ) {
						$calls = MSC_Igdb_Fixtures::$calls;
						ng_reject( static fn() => IgdbClient::request( 'games', 'fields id; // backoff' ), 'Retry-After backoff avoids another network request', 429 );
						ng_check( $calls === MSC_Igdb_Fixtures::$calls, 'Backoff is shared across requests' );
					}
				}
			}
			MSC_Igdb_Fixtures::$mode = '';
			ng_reset();
			ng_reject( static fn() => GameFields::sanitize( array( array( 'date' => '2099-02-30' ) ) ), 'Impossible game release dates are rejected' );
			ng_reject(
				static fn() => GameFields::sanitize(
					array(
						array(
							'date'      => '2099-01-01',
							'precision' => 'year',
						),
					)
				),
				'Partial release windows cannot invent a calendar day'
			);
		}
	);
	ng_drain();
	ng_at(
		$sites['d'],
		static function () use ( $game, $seed ) {
			ng_publish( $game['uuid'] );
			$r = Presentation::record( $game['uuid'] );
			ng_check( array( 'PlayStation 5' ) === $r['data']['platforms'] && '2099-02-08' === $r['data']['release_date'] && 2 === count( $r['data']['release_dates'] ), 'Site D projects only Sony labels and release dates' );
			ng_check( ! array_filter( ReleaseCalendar::data( '2099-01' )['events'], static fn( $e ) => $e['uuid'] === $game['uuid'] ), 'PC date does not enter Site D calendar' );
			$events = array_values( array_filter( ReleaseCalendar::data( '2099-02' )['events'], static fn( $e ) => $e['uuid'] === $game['uuid'] ) );
			ng_check( 1 === count( $events ) && array( 'PlayStation 5' ) === $events[0]['platforms'], 'Sony exact date enters Site D calendar once; year-only window stays out' );
			ng_reset();
			ng_reject( static fn() => IgdbSource::details( (string) ( $seed + 1 ) ), 'Direct non-Sony import is rejected on Site D' );
			ng_reset();
			$partial = OpenImport::preview( 'igdb', (string) ( $seed + 2 ), 'msc_game' );
			ng_reject(
				static fn() => OpenImport::import(
					array(
						'token'  => $partial['token'],
						'nonce'  => wp_create_nonce( 'msc_open_import' ),
						'fields' => array( 'title' ),
					)
				),
				'Site D cannot create an invisible game by omitting every Sony platform field',
				400
			);
			ng_reset();
			OpenSources::search( 'msc_game', 'Sony fixture ' . $seed, 'igdb', 0 );
			ng_check( str_contains( MSC_Igdb_Fixtures::$last['body'], 'where platforms = (' ) && str_contains( MSC_Igdb_Fixtures::$last['body'], '46' ), 'Site D IGDB title searches request Sony platforms and handhelds' );
		}
	);
	ng_at(
		$sites['c'],
		static function () use ( $game, &$pc ) {
			ng_check( count( array_filter( ReleaseCalendar::data( '2099-01' )['events'], static fn( $e ) => $e['uuid'] === $game['uuid'] ) ) === 1, 'Site C retains the earlier PC date' );
			$pc = Plugin::store()->create(
				'msc_game',
				array(
					'title'        => 'PC-only calendar fixture ' . wp_generate_uuid4(),
					'platforms'    => array( 'PC (Microsoft Windows)' ),
					'platform_ids' => array( 6 ),
					'release_date' => '2099-02-14',
				)
			);
			$r  = Plugin::store()->resolved( $game['uuid'] );
			Plugin::store()->local( $r['uuid'], array( 'release_date' => '2099-03-17' ), array(), (int) $r['mapping']['local_version'] );
			ng_check( 1 === count( array_filter( ReleaseCalendar::data( '2099-03' )['events'], static fn( $e ) => $e['uuid'] === $game['uuid'] ) ), 'Effective local date override moves a game to its own month' );
			$before = Plugin::store()->get( $game['uuid'] );
			Schema::participate( get_current_blog_id(), array() );
			ng_check( null === Presentation::record( $game['uuid'] ) && ! ReleaseCalendar::data( '2099-03' )['events'], 'Turning a type off immediately hides detail helpers and calendar before queue delivery' );
			Schema::participate( get_current_blog_id(), array( 'msc_game' ) );
			ng_drain();
			ng_check( $before === Plugin::store()->get( $game['uuid'] ) && '2099-03-17' === Presentation::record( $game['uuid'] )['data']['release_date'], 'Re-enabling restores catalog with global data and local override intact' );
		}
	);
	ng_drain();
	ng_check( ! Plugin::store()->mapping( (int) $pc['id'], $sites['d'] ), 'PC-only records never materialize on Site D' );
	ng_at(
		$sites['a'],
		static function () use ( &$movie ) {
			$movie = Plugin::store()->create(
				'msc_movie',
				array(
					'title'                  => 'Calendar fixture movie ' . wp_generate_uuid4(),
					'release_date'           => '2099-04-03',
					'digital_release_date'   => '2099-04-05',
					'streaming_release_date' => '2099-05-02',
				)
			);
			ng_drain();
			ng_publish( $movie['uuid'] );
			$events = array_values( array_filter( ReleaseCalendar::data( '2099-04' )['events'], static fn( $e ) => $e['uuid'] === $movie['uuid'] ) );
			ng_check( 2 === count( $events ) && $events[0]['kind'] !== $events[1]['kind'], 'Movie theatrical and digital releases populate independently' );
			$r = Plugin::store()->resolved( $movie['uuid'] );
			Plugin::store()->local( $r['uuid'], array(), array(), (int) $r['mapping']['local_version'], 'draft' );
			ng_check( ! array_filter( ReleaseCalendar::data( '2099-04' )['events'], static fn( $e ) => $e['uuid'] === $movie['uuid'] ), 'Draft titles disappear from calendar' );
			ng_publish( $movie['uuid'] );
			$r                         = Plugin::store()->resolved( $movie['uuid'] );
			$old                       = MSC\Materializer::$writing;
			MSC\Materializer::$writing = true;
			wp_update_post(
				array(
					'ID'            => $r['mapping']['post_id'],
					'post_password' => 'private',
				)
			);
			MSC\Materializer::$writing = $old;
			ng_check( ! array_filter( ReleaseCalendar::data( '2099-04' )['events'], static fn( $e ) => $e['uuid'] === $movie['uuid'] ), 'Password-protected titles do not leak through calendar' );
			ng_reject( static fn() => ReleaseCalendar::month( '2099-13' ), 'Calendar rejects invalid months' );
			ng_reject( static fn() => ReleaseCalendar::data( '2099-04', 'msc_game' ), 'Calendar cannot query a disabled type' );
			ng_check( ! Schema::enabled( 'msc_game' ) && Schema::enabled( 'msc_person' ), 'Site A remains film/TV/People after game operations' );
		}
	);
	wp_set_current_user( 0 );
	ng_at(
		$sites['d'],
		static function () use ( $game, $pc ) {
			$q        = new WP_REST_Request( 'GET', '/msc/v1/records/' . $game['uuid'] );
			$response = rest_do_request( $q );
			ng_check( 200 === $response->get_status() && array( 'PlayStation 5' ) === $response->get_data()['data']['platforms'], 'Anonymous canonical REST exposes only Sony game fields' );
			ng_check( ! Rest::can_read( array( 'uuid' => $pc['uuid'] ) ), 'Anonymous REST cannot read PC-only records on Site D' );
			$q = new WP_REST_Request( 'GET', '/msc/v1/calendar' );
			$q->set_param( 'month', '2099-02' );
			$response = rest_do_request( $q );
			ng_check( 200 === $response->get_status() && isset( $response->get_data()['html'] ), 'Anonymous calendar endpoint returns month data and rendered controls' );
		}
	);
} finally {
	wp_set_current_user( 1 );
	NetworkSites::apply( $saved );
	ng_drain();
	if ( false === $old_creds ) {
		delete_network_option( get_current_network_id(), 'msc_igdb_credentials' );
	} else {
		update_network_option( get_current_network_id(), 'msc_igdb_credentials', $old_creds );
	}
	if ( false === $old_seed ) {
		delete_network_option( get_current_network_id(), 'msc_igdb_fixture_seed' );
	} else {
		update_network_option( get_current_network_id(), 'msc_igdb_fixture_seed', $old_seed );
	}
	ng_reset();
}
WP_CLI::success( $GLOBALS['ng_checks'] . ' game, site policy, IGDB and calendar checks passed.' );
