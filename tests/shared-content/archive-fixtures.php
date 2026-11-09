<?php
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local network required.' );
}
wp_set_current_user( 1 );
require __DIR__ . '/network-profiles.php';
$sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = \MSC\NetworkSites::key( $site->path );
	if ( $key ) {
		$sites[ $key ] = (int) $site->blog_id;
	}
}
function ar_at( int $site, callable $fn ) {
	switch_to_blog( $site );
	try {
		return $fn();
	} finally {
		restore_current_blog();
	}
}
function ar_publish( string $uuid, int $site, string $status = 'publish' ): array {
	return ar_at(
		$site,
		static function () use ( $uuid, $site, $status ) {
			$s = \MSC\Plugin::store();
			$r = $s->get( $uuid );
			\MSC\Plugin::materializer()->sync( (int) $r['id'], $site );
			$m = $s->mapping( (int) $r['id'] );
			return $s->local( $uuid, array(), array(), (int) $m['local_version'], $status );
		}
	);
}
function ar_create( int $site, string $type, array $data, string $status = 'publish' ): array {
	$r = ar_at( $site, static fn() => \MSC\Plugin::store()->create( $type, $data ) );
	return ar_publish( $r['uuid'], $site, $status );
}
$tag         = substr( wp_generate_uuid4(), 0, 8 );
$prefix      = 'Archive ' . $tag;
$excerpt     = 'EXCERPT_SENTINEL_' . $tag;
$description = 'DESCRIPTION_SENTINEL_' . $tag;
$demo        = get_network_option( get_current_network_id(), 'msc_network_demo_200', array() );
$movie_art   = \MSC\Plugin::store()->get( $demo['movie'] ?? 0 )['data']['poster'] ?? array();
$show_art    = \MSC\Plugin::store()->get( $demo['show'] ?? 0 )['data']['poster'] ?? array();
$person_art  = \MSC\Plugin::store()->get( $demo['person'] ?? 0 )['data']['headshot'] ?? array();
$movies      = array(); for ( $i = 1; $i <= 21; ++$i ) {
	$movies[] = ar_create(
		$sites['a'],
		'msc_movie',
		array(
			'title'          => $prefix . ' Movie ' . sprintf( '%02d', $i ),
			'excerpt'        => $excerpt,
			'content'        => '<p>' . $description . '</p>',
			'genres'         => array( $i % 2 ? 'Science Fiction' : 'Drama' ),
			'release_date'   => 21 === $i ? '' : ( $i % 2 ? '2090' : '2091' ) . '-01-' . sprintf( '%02d', $i ),
			'runtime'        => 90 + $i,
			'content_rating' => 'PG-13',
			'poster'         => $i % 2 ? $movie_art : $show_art,
		)
	);
}
$hidden = array(); foreach ( array( 'draft', 'private', 'password', 'archived' ) as $kind ) {
	$r = ar_create(
		$sites['a'],
		'msc_movie',
		array(
			'title'        => $prefix . ' Hidden ' . $kind,
			'genres'       => array( 'PRIVATE_GENRE_' . $tag ),
			'release_date' => '2099-12-01',
		),
		in_array( $kind, array( 'draft', 'private' ), true ) ? $kind : 'publish'
	);
	ar_at(
		$sites['a'],
		static function () use ( $r, $kind ) {
			if ( 'password' === $kind ) {
				$old                        = \MSC\Materializer::$writing;
				\MSC\Materializer::$writing = true;
				try {
					wp_update_post(
						array(
							'ID'            => $r['mapping']['post_id'],
							'post_password' => 'fixture-password',
						)
					);
				} finally {
					\MSC\Materializer::$writing = $old;
				}
			}
			if ( 'archived' === $kind ) {
				\MSC\Plugin::store()->update( $r['id'], array(), (int) $r['version'], 'archived' );
			}
		}
	);
	$hidden[] = $r;
}
ar_publish( $movies[0]['uuid'], $sites['b'] );
ar_at(
	$sites['b'],
	static function () use ( $movies, $prefix ) {
		$s = \MSC\Plugin::store();
		$r = $s->resolved( $movies[0]['uuid'] );
		$s->local(
			$r['uuid'],
			array(
				'title'        => $prefix . ' Local Movie',
				'release_date' => '2095-03-04',
				'genres'       => array( 'Mystery' ),
			),
			array(),
			(int) $r['mapping']['local_version']
		);
	}
);
$show     = ar_create(
	$sites['a'],
	'msc_show',
	array(
		'title'              => $prefix . ' Series',
		'genres'             => array( 'Science Fiction' ),
		'premiere_date'      => '2085-04-03',
		'number_of_seasons'  => 3,
		'number_of_episodes' => 24,
		'network'            => 'Demo Network',
		'status'             => 'Returning Series',
		'poster'             => $show_art,
		'excerpt'            => $excerpt,
	)
);
$episode  = ar_create(
	$sites['a'],
	'msc_episode',
	array(
		'title'          => $prefix . ' Episode',
		'parent_show'    => $show['uuid'],
		'season_number'  => 2,
		'episode_number' => 1,
		'runtime'        => 42,
		'release_date'   => '2086-01-02',
		'excerpt'        => $excerpt,
	)
);
$person   = ar_create(
	$sites['a'],
	'msc_person',
	array(
		'title'       => $prefix . ' Person',
		'professions' => array( 'Actor', 'Director' ),
		'country'     => 'United States',
		'headshot'    => $person_art,
		'excerpt'     => $excerpt,
	)
);
$entities = array();
foreach ( \MSC\EntityCatalog::definitions() as $type => $definition ) {
	$site   = in_array( $type, array( 'msc_developer', 'msc_publisher', 'msc_console' ), true ) ? $sites['c'] : $sites['a'];
	$values = array(
		'title'        => $prefix . ' ' . $definition[1],
		'country'      => 'United States',
		'headquarters' => 'Los Angeles',
		'excerpt'      => $excerpt,
	);
	if ( 'msc_console' === $type ) {
		$values += array(
			'platform_id'   => 167,
			'manufacturer'  => 'Sony',
			'platform_kind' => 'Home console',
			'release_date'  => '2094-09-01',
		);
	}
	$entities[ $type ] = ar_create( $site, $type, $values );
}
$game = ar_create(
	$sites['c'],
	'msc_game',
	array(
		'title'         => $prefix . ' Game',
		'excerpt'       => $excerpt,
		'poster'        => $show_art,
		'genres'        => array( 'Adventure' ),
		'platforms'     => array( 'PC (Microsoft Windows)', 'PlayStation 5' ),
		'platform_ids'  => array( 6, 167 ),
		'release_date'  => '2088-01-01',
		'release_dates' => array(
			array(
				'date'        => '2088-01-01',
				'precision'   => 'day',
				'platform'    => 'PC (Microsoft Windows)',
				'platform_id' => 6,
				'region'      => 'Worldwide',
			),
			array(
				'date'        => '2094-02-02',
				'precision'   => 'day',
				'platform'    => 'PlayStation 5',
				'platform_id' => 167,
				'region'      => 'Worldwide',
			),
		),
	)
);
ar_publish( $game['uuid'], $sites['d'] );
$pc = ar_create(
	$sites['c'],
	'msc_game',
	array(
		'title'        => $prefix . ' PC Hidden',
		'genres'       => array( 'PC_ONLY_' . $tag ),
		'platform_ids' => array( 6 ),
		'platforms'    => array( 'PC (Microsoft Windows)' ),
		'release_date' => '2087-01-01',
	)
);
update_blog_option( $sites['d'], 'msc_game_platform_policy', 'all' );
try {
	ar_publish( $pc['uuid'], $sites['d'] );
} finally {
	update_blog_option( $sites['d'], 'msc_game_platform_policy', 'sony_all' );
}
$fixture = compact( 'sites', 'prefix', 'tag', 'excerpt', 'description', 'movies', 'hidden', 'show', 'episode', 'person', 'entities', 'game', 'pc' );
if ( ! defined( 'MSC_ARCHIVE_FIXTURE_INCLUDE' ) ) {
	echo wp_json_encode( $fixture );
}
