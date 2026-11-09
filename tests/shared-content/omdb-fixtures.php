<?php
/**
 * Plugin Name: MSC OMDb Disposable Test Fixtures
 * Description: Activated only by the explicitly disposable browser-test helper.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'MSC_OMDB_TEST_FIXTURES' ) || true !== MSC_OMDB_TEST_FIXTURES || 'local' !== wp_get_environment_type() ) {
	return;
}
final class MSC_Omdb_Fixtures {
	public static int $requests    = 0;
	public static array $details   = array();
	public static bool $bad_poster = false;
	public static function catalog(): array {
		$base       = array(
			'Response'   => 'True',
			'Rated'      => 'PG-13',
			'Released'   => '31 Mar 1999',
			'Runtime'    => '136 min',
			'Genre'      => 'Action, Science Fiction',
			'Director'   => 'Lana Wachowski, Lilly Wachowski',
			'Writer'     => 'Lilly Wachowski, Lana Wachowski',
			'Actors'     => 'Keanu Reeves, Laurence Fishburne',
			'Plot'       => 'A computer programmer discovers that his world is a simulation.',
			'Language'   => 'English, French',
			'Country'    => 'United States, Australia',
			'Awards'     => 'Won 4 Oscars.',
			'Poster'     => 'https://m.media-amazon.com/images/M/msc-omdb-fixture.jpg',
			'Ratings'    => array(
				array(
					'Source' => 'Internet Movie Database',
					'Value'  => '8.7/10',
				),
			),
			'Metascore'  => '73',
			'imdbRating' => '8.7',
			'imdbVotes'  => '2,000,000',
			'Type'       => 'movie',
			'DVD'        => '21 Sep 1999',
			'BoxOffice'  => '$172,076,928',
			'Production' => 'Fixture Pictures',
			'Website'    => 'https://example.com/',
		);
		$catalog    = array(
			'tt0133093' => $base + array(
				'Title'  => 'The Matrix',
				'Year'   => '1999',
				'imdbID' => 'tt0133093',
			),
			'tt0234215' => array_replace(
				$base,
				array(
					'Title'    => 'The Matrix Reloaded',
					'Year'     => '2003',
					'Released' => '15 May 2003',
					'imdbID'   => 'tt0234215',
				)
			),
			'tt9000010' => array_replace(
				$base,
				array(
					'Title'        => 'OMDb Fixture Signal',
					'Year'         => '2020–2022',
					'imdbID'       => 'tt9000010',
					'Type'         => 'series',
					'totalSeasons' => '2',
					'Actors'       => 'Fixture Actor',
					'Director'     => 'N/A',
					'Writer'       => 'Fixture Writer',
					'Released'     => '01 Jan 2020',
				)
			),
			'tt9000011' => array_replace(
				$base,
				array(
					'Title'    => 'OMDb Fixture Pilot',
					'Year'     => '2020',
					'imdbID'   => 'tt9000011',
					'Type'     => 'episode',
					'seriesID' => 'tt9000010',
					'Season'   => '1',
					'Episode'  => '1',
					'Actors'   => 'Fixture Actor',
					'Writer'   => 'Fixture Writer',
					'Director' => 'Fixture Director',
					'Runtime'  => '48 min',
					'Released' => '15 Jan 2020',
				)
			),
			'tt9000030' => array_replace(
				$base,
				array(
					'Title'    => '0',
					'Year'     => '2024',
					'imdbID'   => 'tt9000030',
					'Runtime'  => '0 min',
					'Released' => '01 Jan 2024',
				)
			),
			'tt9000020' => array_replace(
				$base,
				array(
					'Title'    => 'OMDb Partial Fixture',
					'Year'     => '2024',
					'imdbID'   => 'tt9000020',
					'Released' => 'N/A',
					'Runtime'  => 'N/A',
					'Genre'    => 'N/A',
					'Plot'     => 'N/A',
					'Director' => 'N/A',
					'Writer'   => 'N/A',
					'Actors'   => 'N/A',
					'Country'  => 'N/A',
					'Language' => 'N/A',
					'DVD'      => 'N/A',
					'Poster'   => 'N/A',
					'Website'  => 'N/A',
				)
			),
		);
		$browser_id = get_network_option( get_current_network_id(), 'msc_omdb_browser_id', '' );
		if ( preg_match( '/^tt[0-9]{9}$/D', $browser_id ) ) {
			$catalog[ $browser_id ] = array_replace(
				$base,
				array(
					'Title'  => 'OMDb Browser ' . $browser_id,
					'Year'   => '2003',
					'imdbID' => $browser_id,
				)
			);
		}
		return $catalog;
	}
	public static function response( $check, array $args, string $url ) {
		$parts = wp_parse_url( $url );
		$host  = $parts['host'] ?? '';
		if ( 'm.media-amazon.com' === $host && '/images/M/msc-omdb-fixture.jpg' === ( $parts['path'] ?? '' ) ) {
			if ( self::$bad_poster ) {
				$bytes = 'not an image';
			} else {
				$image = imagecreatetruecolor( 64, 96 );
				imagefill( $image, 0, 0, imagecolorallocate( $image, 32, 95, 68 ) );
				ob_start();
				imagejpeg( $image );
				$bytes = ob_get_clean();
				imagedestroy( $image );
			}
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				file_put_contents( $args['filename'], $bytes );
				$bytes = '';
			}
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'content-type' => 'image/jpeg' ),
				'body'     => $bytes,
			);
		}
		if ( 'www.omdbapi.com' !== $host ) {
			return $check;
		}
		++self::$requests;
		parse_str( $parts['query'] ?? '', $query );
		$data = array(
			'Response' => 'False',
			'Error'    => 'Movie not found!',
		);
		if ( isset( $query['i'] ) ) {
			$data = self::$details[ $query['i'] ] ?? self::catalog()[ $query['i'] ] ?? $data;
		} elseif ( isset( $query['s'] ) ) {
			switch ( strtolower( $query['s'] ) ) {
				case 'quota':
					$data['Error'] = 'Request limit reached!';

					break;
				case 'badkey':
					$data['Error'] = 'Invalid API key: ' . ( $query['apikey'] ?? '' );

					break;
				case 'malformed':
					return array(
						'response' => array( 'code' => 200 ),
						'headers'  => array(),
						'body'     => '{not json',
					);
				case 'offline':
					return new WP_Error( 'offline', 'Failed URL: ' . $url );
				case 'paged':
					$data  = array(
						'Response'     => 'True',
						'totalResults' => '12',
						'Search'       => array(),
					);
					$start = 1 === (int) ( $query['page'] ?? 1 ) ? 1 : 11;
					$end   = 1 === $start ? 10 : 12;
					for ( $i = $start; $i <= $end; ++$i ) {
						$data['Search'][] = array(
							'Title'  => 'Paged Title ' . $i,
							'Year'   => '2024',
							'Type'   => 'movie',
							'imdbID' => 'tt' . ( 9100000 + $i ),
							'Poster' => 'N/A',
						);
					}
					break;
				default:
					$results = array();
					foreach ( self::catalog() as $title ) {
						if ( false !== stripos( $title['Title'], $query['s'] ) && ( empty( $query['type'] ) || $query['type'] === $title['Type'] ) && ( empty( $query['y'] ) || str_starts_with( $title['Year'], $query['y'] ) ) ) {
							$results[] = array_intersect_key( $title, array_flip( array( 'Title', 'Year', 'imdbID', 'Type', 'Poster' ) ) );
						}
					}
					if ( $results ) {
						$data = array(
							'Response'     => 'True',
							'totalResults' => (string) count( $results ),
							'Search'       => $results,
						);
					}
			}
		}
		return array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $data ),
		);
	}
}
add_filter( 'pre_http_request', array( MSC_Omdb_Fixtures::class, 'response' ), 10, 3 );
