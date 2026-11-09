<?php
/** Additive fixtures for public season navigation; requires the disposable test network. */
use MSC\{Plugin, Queue, OpenImport};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local network required.' );
}
wp_set_current_user( 1 );
$sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = MSC\NetworkSites::key( $site->path );
	if ( $key ) {
		$sites[ $key ] = (int) $site->blog_id;
	}
}
function season_at( int $site, callable $fn ) {
	switch_to_blog( $site );
	try {
		Plugin::register_posts();
		return $fn();
	} finally {
		restore_current_blog();
	}
}
function season_publish( string $uuid ): void {
	$r = Plugin::store()->resolved( $uuid );
	Plugin::store()->local( $uuid, array(), array(), (int) $r['mapping']['local_version'], 'publish' );
}
$tag                        = substr( wp_generate_uuid4(), 0, 8 );
$seed                       = random_int( 100000, 900000 );
$store                      = Plugin::store();
$records                    = season_at(
	$sites['a'],
	static function () use ( $store, $tag, $seed ) {
		$records = array();
		foreach ( array(
			'show'     => 'msc_show',
			'movie'    => 'msc_movie',
			'person'   => 'msc_person',
			'fallback' => 'msc_movie',
		) as $key => $type ) {
			$text                             = 'Catalog overview ' . $key . ' ' . $tag;
			$records[ $key ]                  = $store->create(
				$type,
				array(
					'title'   => 'Season UI ' . $key . ' ' . $tag,
					'content' => 'fallback' === $key ? '' : '<p>' . $text . '</p>',
					'excerpt' => $text,
				)
			);
			$records[ $key ]['expected_text'] = $text;
		}
		$episodes = array();
		foreach ( array(
			1         => 51,
			7         => 2,
			0         => 1,
			9         => 1,
			'unknown' => 1,
		) as $season => $count ) {
			for ( $i = 1; $i <= $count; ++$i ) {
				$title  = 'Season ' . $season . ' Episode ' . $i . ' ' . $tag;
				$url    = 'https://www.tvmaze.com/episodes/' . ( $seed + $i + ( 'unknown' === $season ? 1000 : 100 * (int) $season ) );
				$source = array(
					'provider'    => 'tvmaze',
					'url'         => $url,
					'attribution' => 'TVmaze test fixture',
					'license'     => 'CC BY-SA 4.0',
					'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
					'changes'     => 'Test fixture.',
				);
				$data   = array(
					'title'               => $title,
					'parent_show'         => $records['show']['uuid'],
					'episode_number'      => $i,
					'excerpt'             => 'Episode overview ' . $tag,
					'content'             => '<p>Episode overview ' . $tag . '</p>',
					'additional_metadata' => array(
						'open_import' => array(
							'fields' => array(
								'title' => array(
									'hash'   => OpenImport::hash( $title ),
									'source' => $source,
								),
							),
						),
					),
				);
				if ( 'unknown' !== $season ) {
					$data['season_number'] = $season;
				}
				$e          = $store->create( 'msc_episode', $data );
				$episodes[] = array(
					'record'  => $e,
					'publish' => 9 !== $season,
				);
			}
		}
		$records['episode']                  = $episodes[0]['record'];
		$records['episode']['expected_text'] = 'Episode overview ' . $tag;
		return array(
			'records'  => $records,
			'episodes' => $episodes,
		);
	}
);
$records['records']['game'] = season_at(
	$sites['c'],
	static function () use ( $store, $tag ) {
		$text               = 'Catalog overview game ' . $tag;
		$r                  = $store->create(
			'msc_game',
			array(
				'title'        => 'Season UI game ' . $tag,
				'content'      => '<p>' . $text . '</p>',
				'excerpt'      => $text,
				'platforms'    => array( 'PlayStation 5' ),
				'platform_ids' => array( 167 ),
			)
		);
		$r['expected_text'] = $text;
		return $r;
	}
);
for ( $i = 0; $i < 100 && Queue::status()['pending']; ++$i ) {
	$r = Queue::run( 1000 );
	if ( $r['errors'] || ! $r['processed'] ) {
		throw new RuntimeException( 'Season fixture synchronization failed.' );
	}
}
$result = array(
	'tag'     => $tag,
	'sites'   => array(),
	'catalog' => array(),
);
foreach ( array( 'a', 'b' ) as $key ) {
	$result['sites'][ $key ] = season_at(
		$sites[ $key ],
		static function () use ( $records, $store ) {
			season_publish( $records['records']['show']['uuid'] );
			foreach ( $records['episodes'] as $e ) {
				if ( $e['publish'] ) {
					season_publish( $e['record']['uuid'] );
				}
			}
			return get_permalink( msc_local_post_id( $records['records']['show']['uuid'] ) );
		}
	);
}
foreach ( $records['records'] as $key => $r ) {
	$result['catalog'][ $key ] = season_at(
		$sites[ 'game' === $key ? 'c' : 'a' ],
		static function () use ( $r ) {
			season_publish( $r['uuid'] );
			return array(
				'url'  => get_permalink( msc_local_post_id( $r['uuid'] ) ),
				'text' => $r['expected_text'],
			);
		}
	);
}
echo wp_json_encode( $result );
