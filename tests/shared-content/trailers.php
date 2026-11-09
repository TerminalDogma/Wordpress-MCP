<?php
use MSC\{Acf, EntityCatalog, ImageSearch, Plugin, Presentation, Schema, SourceError, Trailers};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local network required.' );
}
wp_set_current_user( 1 );
$GLOBALS['trailer_checks'] = 0;
function trailer_check( $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['trailer_checks'];
	WP_CLI::log( 'PASS: ' . $message );
}
function trailer_reject( callable $fn, string $message ): void {
	try {
		$fn();
	} catch ( Throwable $e ) {
		trailer_check( true, $message );
		return;
	}
	throw new RuntimeException( 'FAIL: ' . $message );
}
$one   = 'https://www.youtube.com/watch?v=abcdefghijk';
$two   = 'https://www.youtube.com/watch?v=lmnopqrstuv';
$three = 'https://vimeo.com/123456789';
trailer_check( $one . "\n" . $three === Trailers::sanitize( "https://youtu.be/abcdefghijk?t=30\r\n$one\n$three" ), 'Trailer URLs normalize, deduplicate and retain order' );
foreach ( array( '<iframe src="https://youtube.com/embed/abcdefghijk"></iframe>', 'javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=abcdefghijk', 'https://user@youtube.com/watch?v=abcdefghijk', 'https://youtube.com/watch?v[]=abcdefghijk', 'https://example.com/video' ) as $bad ) {
	trailer_reject( static fn() => Trailers::sanitize( $bad ), 'Unsupported or unsafe trailer input rejected' );
}
trailer_check( '' === Trailers::sanitize( '' ), 'An empty trailer field is supported' );
$many = array(); for ( $i = 0; $i < 13; ++$i ) {
	$many[] = 'https://youtu.be/' . str_pad( (string) $i, 11, 'a' );
}
trailer_reject( static fn() => Trailers::sanitize( implode( "\n", $many ) ), 'Trailer count is bounded' );
$schemas = Schema::all();
$version = get_network_option( get_current_network_id(), 'msc_builtin_schema_version' );
$old     = $schemas;
try {
	foreach ( array( 'msc_movie', 'msc_show', 'msc_game' ) as $type ) {
		unset( $old[ $type ]['fields']['trailers'] );
	}
	$old['msc_movie']['label'] = 'Custom Movie Label';
	update_network_option( get_current_network_id(), 'msc_schemas', $old );
	update_network_option( get_current_network_id(), 'msc_builtin_schema_version', 6 );
	Schema::upgrade_defaults();
	trailer_check( isset( Schema::get( 'msc_movie' )['fields']['trailers'], Schema::get( 'msc_show' )['fields']['trailers'], Schema::get( 'msc_game' )['fields']['trailers'] ) && 'Custom Movie Label' === Schema::get( 'msc_movie' )['label'], 'An existing network receives trailer fields without losing custom schema labels' );
} finally {
	update_network_option( get_current_network_id(), 'msc_schemas', $schemas );
	update_network_option( get_current_network_id(), 'msc_builtin_schema_version', $version );
}
foreach ( array( 'msc_movie', 'msc_show', 'msc_game' ) as $type ) {
	trailer_check( 'textarea' === acf_get_field( Acf::key( $type, 'trailers' ) )['type'], 'ACF Free provides a multiline trailer editor for ' . $type );
}
$old_key = get_network_option( get_current_network_id(), 'msc_image_search_key', false );
$old_env = getenv( 'SERPER_API_KEY' );
putenv( 'SERPER_API_KEY' );
ImageSearch::save_key( 'fixture-trailers-' . wp_generate_uuid4() );
$calls = 0;
$mock  = static function ( $pre, $args, $url ) use ( &$calls, $one, $two ) {
	if ( ! str_starts_with( $url, 'https://google.serper.dev/' ) ) {
		return $pre;
	}
	++$calls;
	$body = json_decode( $args['body'], true );
	if ( str_ends_with( $url, '/videos' ) ) {
		trailer_check( str_contains( $body['q'], 'site:youtube.com/watch' ), 'Provider queries are scoped to YouTube videos' );
	}
	if ( str_contains( $body['q'], 'quota-fixture' ) ) {
		return array(
			'response' => array( 'code' => 429 ),
			'headers'  => array( 'retry-after' => '2' ),
			'body'     => '{}',
		);
	}
	$rows = array(
		array(
			'link'     => $one,
			'title'    => '<b>Official Trailer</b>',
			'channel'  => 'Studio',
			'duration' => '2:30',
			'imageUrl' => 'http://localhost/private',
		),
		array(
			'link'  => 'https://youtu.be/abcdefghijk',
			'title' => 'Duplicate',
		),
		array( 'link' => 'https://example.com/video' ),
		array(
			'link'  => $two,
			'title' => 'Trailer Two',
		),
	);
	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(),
		'body'     => wp_json_encode( str_ends_with( $url, '/videos' ) ? array( 'videos' => $rows ) : array( 'images' => array() ) ),
	);
};
add_filter( 'pre_http_request', $mock, 10, 3 );
try {
	$query = 'trailer fixture ' . wp_generate_uuid4();
	$r     = ImageSearch::search( $query, 1, 'videos' );
	trailer_check( 2 === count( $r['items'] ) && 'Official Trailer' === $r['items'][0]['title'] && str_starts_with( $r['items'][0]['thumbnail'], 'https://i.ytimg.com/' ), 'Search returns sanitized, unique YouTube videos and safe thumbnails' );
	trailer_check( $r === ImageSearch::search( $query, 1, 'videos' ) && 1 === $calls, 'Repeated video searches use the server cache' );
	ImageSearch::search( $query, 2, 'videos' );
	trailer_check( 2 === $calls, 'Search pages use separate caches' );
	trailer_check( array() === ImageSearch::search( $query )['items'] && 3 === $calls, 'Video and image caches remain independent' );
	wp_set_current_user( 0 );
	trailer_reject( static fn() => ImageSearch::search( $query, 1, 'videos' ), 'Anonymous searches cannot read cached results' );
	wp_set_current_user( 1 );
	trailer_reject( static fn() => ImageSearch::search( 'quota-fixture ' . $query, 1, 'videos' ), 'Provider quota failures are handled' );
	$before = $calls;
	trailer_reject( static fn() => ImageSearch::search( 'different ' . $query, 1, 'videos' ), 'Provider cooldown is respected' );
	trailer_check( $before === $calls && $r === ImageSearch::search( $query, 1, 'videos' ), 'Cooldown avoids upstream calls while allowing cached results' );
} finally {
	remove_filter( 'pre_http_request', $mock, 10 );
	wp_set_current_user( 1 );
	false === $old_key ? delete_network_option( get_current_network_id(), 'msc_image_search_key' ) : update_network_option( get_current_network_id(), 'msc_image_search_key', $old_key );
	false === $old_env ? putenv( 'SERPER_API_KEY' ) : putenv( 'SERPER_API_KEY=' . $old_env );
}
$sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$sites[ $site->path ] = (int) $site->blog_id;
}
$fixtures = array();
foreach ( array(
	'msc_movie' => '/site-a/',
	'msc_show'  => '/site-a/',
	'msc_game'  => '/site-c/',
) as $type => $path ) {
	switch_to_blog( $sites[ $path ] );
	try {
		$s      = Plugin::store();
		$tag    = substr( wp_generate_uuid4(), 0, 8 );
		$actors = array();
		$names  = array();
		if ( 'msc_movie' === $type ) {
			for ( $i = 1; $i <= 7; ++$i ) {
				$p = $s->create( 'msc_person', array( 'title' => 'Actor ' . $i . ' ' . $tag ) );
				Plugin::materializer()->sync( (int) $p['id'], get_current_blog_id() );
				$actors[] = $p['uuid'];
				$names[]  = $p['data']['title'];
			}
		}
		$data = array(
			'title'    => 'Trailer ' . $type . ' ' . $tag,
			'content'  => '<p>Overview for trailer layout verification.</p>',
			'trailers' => $one . "\n" . $two . "\n" . $three,
		);
		if ( 'msc_movie' === $type ) {
			$data += array(
				'actors'   => $actors,
				'language' => 'English',
				'cast'     => implode( ', ', $names ),
			);
		}
		if ( 'msc_game' === $type ) {
			$data += array(
				'platforms'    => array( 'PlayStation 5' ),
				'platform_ids' => array( 167 ),
			);
		}
		$r = $s->create( $type, $data );
		Plugin::materializer()->sync( (int) $r['id'], get_current_blog_id() );
		$m = $s->mapping( (int) $r['id'] );
		$s->local( $r['uuid'], array(), array(), (int) $m['local_version'], 'publish' );
		$public = Presentation::record( $r['uuid'] );
		trailer_check( null !== $public, 'Trailer fixture is public on its participating site: ' . $type );
		ob_start();
		Trailers::render( $public );
		$html = ob_get_clean();
		trailer_check( 3 === substr_count( $html, '<iframe ' ) && 1 === preg_match_all( '/<iframe src=/', $html ) && 3 === substr_count( $html, ' hidden' ), 'Only the first trailer player loads initially: ' . $type );
		$empty                     = $public;
		$empty['data']['trailers'] = '';
		ob_start();
		Trailers::render( $empty );
		trailer_check( '' === ob_get_clean(), 'Empty trailer areas are hidden: ' . $type );
		if ( 'msc_movie' === $type ) {
			$sidebar = EntityCatalog::sidebar( $public );
			trailer_check( str_contains( $sidebar, $names[4] ) && ! str_contains( $sidebar, $names[5] ) && str_contains( $sidebar, 'href="#cast"' ), 'The sidebar includes five credited actors and a full-cast link' );
			ob_start();
			Presentation::credits( $public );
			$cast = ob_get_clean();
			trailer_check( str_contains( $cast, 'id="cast"' ) && str_contains( $cast, $names[6] ) && 7 === count( $s->get( $r['uuid'] )['data']['actors'] ), 'Full cast and saved relationship data retain all actors' );
			update_field( Acf::key( $type, 'trailers' ), $three, $m['post_id'] );
			trailer_check( $three === $s->resolved( $r['uuid'] )['data']['trailers'] && $data['trailers'] === $s->get( $r['uuid'] )['data']['trailers'], 'ACF saves a local trailer override without changing the global value' );
			$current = $s->mapping( (int) $r['id'] );
			$s->local( $r['uuid'], array(), array( 'trailers' ), (int) $current['local_version'] );
			trailer_check( $data['trailers'] === $s->resolved( $r['uuid'] )['data']['trailers'], 'Resetting the trailer override restores inheritance' );
		}
		$fixtures[ $type ] = array(
			'url'   => Presentation::url( $public ),
			'acf'   => admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] ),
			'full'  => admin_url( 'admin.php?page=msc&mode=global&record=' . $r['uuid'] ),
			'names' => $names,
		);
	} finally {
		restore_current_blog();
	}
}
WP_CLI::success( $GLOBALS['trailer_checks'] . ' trailer, search and sidebar checks passed.' );
WP_CLI::log( 'MSC_TRAILER_FIXTURE:' . wp_json_encode( $fixtures ) );
