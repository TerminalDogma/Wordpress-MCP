<?php
/* Plugin Name: MSC IGDB fixtures (disposable local tests only) */
if ( ! defined( 'MSC_IGDB_TEST_FIXTURES' ) || ! MSC_IGDB_TEST_FIXTURES || 'local' !== wp_get_environment_type() ) {
	return;
}
final class MSC_Igdb_Fixtures {
	public static int $auth    = 0;
	public static int $calls   = 0;
	public static string $mode = '';
	public static array $last  = array();
	public static function seed(): int {
		return (int) get_network_option( get_current_network_id(), 'msc_igdb_fixture_seed', 880000100 );
	}
	public static function game( int $id ): array {
		$pc = $id === self::seed() + 1;
		return array(
			'id'                 => $id,
			'name'               => ( $pc ? 'Fixture PC Game ' : 'Fixture IGDB Game ' ) . $id,
			'slug'               => 'fixture-game-' . $id,
			'summary'            => 'A fictional game summary for importer verification.',
			'storyline'          => 'An original test story.',
			'cover'              => array( 'image_id' => 'fixture_cover' ),
			'platforms'          => $pc ? array(
				array(
					'id'   => 6,
					'name' => 'PC (Microsoft Windows)',
				),
			) : array(
				array(
					'id'   => 167,
					'name' => 'PlayStation 5',
				),
				array(
					'id'   => 6,
					'name' => 'PC (Microsoft Windows)',
				),
			),
			'genres'             => array( array( 'name' => 'Adventure' ) ),
			'game_modes'         => array( array( 'name' => 'Single player' ) ),
			'involved_companies' => array(
				array(
					'company'   => array( 'name' => 'Fixture Studio' ),
					'developer' => true,
					'publisher' => true,
				),
			),
			'release_dates'      => array(
				array(
					'id'             => $id + 10,
					'date'           => 4071513600,
					'y'              => 2099,
					'm'              => 1,
					'd'              => 6,
					'human'          => 'Jan 6, 2099',
					'date_format'    => array( 'format' => 'YYYY-MM-DD' ),
					'release_region' => array( 'region' => 'north_america' ),
					'platform'       => array(
						'id'   => 6,
						'name' => 'PC (Microsoft Windows)',
					),
				),
				array(
					'id'             => $id + 11,
					'y'              => 2099,
					'm'              => 2,
					'd'              => 8,
					'human'          => 'Feb 8, 2099',
					'date_format'    => array( 'format' => 'YYYY-MM-DD' ),
					'release_region' => array( 'region' => 'worldwide' ),
					'platform'       => array(
						'id'   => $pc ? 6 : 167,
						'name' => $pc ? 'PC (Microsoft Windows)' : 'PlayStation 5',
					),
				),
				array(
					'id'          => $id + 12,
					'date'        => 4070908800,
					'y'           => 2099,
					'human'       => '2099',
					'date_format' => array( 'format' => 'YYYY' ),
					'platform'    => array(
						'id'   => $pc ? 6 : 167,
						'name' => $pc ? 'PC (Microsoft Windows)' : 'PlayStation 5',
					),
				),
			),
		);
	}
	public static function http( $pre, array $args, string $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'id.twitch.tv', 'api.igdb.com', 'images.igdb.com' ), true ) ) {
			return $pre;
		}
		if ( 'id.twitch.tv' === $host ) {
			++self::$auth;
			return self::response(
				array(
					'access_token' => 'fixture-token-' . self::$auth,
					'expires_in'   => 3600,
					'token_type'   => 'bearer',
				)
			);
		}
		if ( 'images.igdb.com' === $host ) {
			$body = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
			if ( ! empty( $args['stream'] ) ) {
				file_put_contents( $args['filename'], $body );
				$body = '';
			}
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'image/png' ),
				'body'     => $body,
			);
		}
		++self::$calls;
		self::$last = $args;
		if ( '401' === self::$mode ) {
			self::$mode = '';
			return self::response( array(), 401 );
		}
		if ( '429' === self::$mode ) {
			return array(
				'response' => array( 'code' => 429 ),
				'headers'  => array( 'retry-after' => '4' ),
				'body'     => '',
			);
		}
		if ( 'offline' === self::$mode ) {
			return new WP_Error( 'offline', 'Fixture transport failure with private-secret-that-must-not-leak' );
		}
		if ( 'malformed' === self::$mode ) {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => 'not json',
			);
		}
		if ( preg_match( '/where id = ([0-9]+);/', $args['body'], $m ) ) {
			return self::response( array( self::game( (int) $m[1] ) ) );
		}
		return self::response( array( self::game( self::seed() ) ) );
	}
	private static function response( array $data, int $status = 200 ): array {
		return array(
			'response' => array( 'code' => $status ),
			'headers'  => array(),
			'body'     => wp_json_encode( $data ),
		);
	}
}
add_filter( 'pre_http_request', array( MSC_Igdb_Fixtures::class, 'http' ), 10, 3 );
