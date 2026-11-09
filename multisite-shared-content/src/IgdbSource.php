<?php
namespace MSC;

final class IgdbSource {
	private const FIELDS = 'id,name,slug,summary,storyline,url,updated_at,cover.image_id,platforms.id,platforms.name,genres.name,game_modes.name,involved_companies.company.name,involved_companies.developer,involved_companies.publisher,release_dates.id,release_dates.date,release_dates.human,release_dates.y,release_dates.m,release_dates.d,release_dates.date_format.format,release_dates.release_region.region,release_dates.platform.id,release_dates.platform.name';
	public static function source( string $url ): array {
		return array(
			'provider'    => 'igdb',
			'url'         => $url,
			'attribution' => 'IGDB',
			'license'     => 'IGDB API terms',
			'license_url' => 'https://api-docs.igdb.com/#partnership',
			'changes'     => 'Selected and formatted for this catalog.',
		);
	}
	public static function search( string $query, int $offset ): array {
		$where = 'all' === GamePolicy::mode() ? '' : 'where platforms = (' . implode( ',', array_keys( GamePolicy::sony() ) ) . '); ';
		$rows  = IgdbClient::request( 'games', 'search ' . wp_json_encode( $query, JSON_UNESCAPED_UNICODE ) . '; fields id,name,summary,platforms.name; ' . $where . 'limit 20; offset ' . $offset . ';' );
		$items = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['id'] ) || ! is_numeric( $row['id'] ) || empty( $row['name'] ) ) {
				continue;
			}
			$items[] = array(
				'provider'    => 'igdb',
				'id'          => (string) $row['id'],
				'title'       => sanitize_text_field( $row['name'] ),
				'description' => sanitize_text_field( implode( ', ', array_column( (array) ( $row['platforms'] ?? array() ), 'name' ) ) ),
			);
		}
		return array(
			'items' => $items,
			'next'  => count( $rows ) === 20 && $offset < 200 ? $offset + 20 : null,
		);
	}
	public static function releases( array $rows ): array {
		$out = array(); foreach ( array_slice( $rows, 0, 1000 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$format = is_array( $row['date_format'] ?? null ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $row['date_format']['format'] ?? '' ) ) : '';
			if ( '' === $format && isset( $row['category'] ) ) {
				$format = array(
					0 => 'YYYYMMDD',
					1 => 'YYYYMM',
					2 => 'YYYY',
					3 => 'YYYYQ1',
					4 => 'YYYYQ2',
					5 => 'YYYYQ3',
					6 => 'YYYYQ4',
					7 => 'TBD',
				)[ (int) $row['category'] ] ?? '';
			}
			$precision = in_array( $format, array( 'YYYYMMDD', 'YYYYMMMMDD' ), true ) ? 'day' : ( in_array( $format, array( 'YYYYMM', 'YYYYMMMM' ), true ) ? 'month' : ( 'YYYY' === $format ? 'year' : ( str_contains( $format, 'Q' ) ? 'quarter' : 'tbd' ) ) );
			$date      = '';
			if ( 'day' === $precision ) {
				if ( isset( $row['y'], $row['m'], $row['d'] ) && checkdate( (int) $row['m'], (int) $row['d'], (int) $row['y'] ) ) {
					$date = sprintf( '%04d-%02d-%02d', $row['y'], $row['m'], $row['d'] );
				} elseif ( is_numeric( $row['date'] ?? null ) && (int) $row['date'] > 0 ) {
					$date = gmdate( 'Y-m-d', (int) $row['date'] );
				}
			}
			if ( 'day' === $precision && ! ReleaseCalendar::date( $date ) ) {
				$precision = 'tbd';
				$date      = '';
			}
			$platform = is_array( $row['platform'] ?? null ) ? $row['platform'] : array( 'id' => (int) ( $row['platform'] ?? 0 ) );
			$region   = is_array( $row['release_region'] ?? null ) ? (string) ( $row['release_region']['region'] ?? '' ) : '';
			if ( '' === $region && isset( $row['region'] ) ) {
				$region = array(
					1  => 'Europe',
					2  => 'North America',
					3  => 'Australia',
					4  => 'New Zealand',
					5  => 'Japan',
					6  => 'China',
					7  => 'Asia',
					8  => 'Worldwide',
					9  => 'Korea',
					10 => 'Brazil',
				)[ (int) $row['region'] ] ?? '';
			}
			$region = ucwords( str_replace( '_', ' ', sanitize_text_field( $region ) ) );
			$out[]  = array(
				'source_id'   => (int) ( $row['id'] ?? 0 ),
				'date'        => $date,
				'precision'   => $precision,
				'display'     => sanitize_text_field( $row['human'] ?? ( $date ?: 'TBD' ) ),
				'platform_id' => (int) ( $platform['id'] ?? 0 ),
				'platform'    => sanitize_text_field( $platform['name'] ?? '' ),
				'region'      => $region,
			);
		}
		return $out;
	}
	public static function details( string $id ): array {
		if ( ! preg_match( '/^[1-9][0-9]{0,11}$/D', $id ) ) {
			throw new SourceError( 'Invalid IGDB game identifier.' );
		}
		$rows = IgdbClient::request( 'games', 'fields ' . self::FIELDS . '; where id = ' . $id . '; limit 1;' );
		$g    = $rows[0] ?? array();
		if ( (string) ( $g['id'] ?? '' ) !== $id || empty( $g['name'] ) ) {
			throw new SourceError( 'IGDB did not return the requested game.', 404 );
		}
		$title   = sanitize_text_field( $g['name'] );
		$url     = 'https://www.igdb.com/games/' . ( ! empty( $g['slug'] ) ? sanitize_title( $g['slug'] ) : $id );
		$source  = self::source( $url );
		$fields  = array(
			'title'         => $title,
			'platforms'     => array_values( array_unique( array_map( 'sanitize_text_field', array_column( (array) ( $g['platforms'] ?? array() ), 'name' ) ) ) ),
			'platform_ids'  => array_map( 'intval', array_column( (array) ( $g['platforms'] ?? array() ), 'id' ) ),
			'release_dates' => self::releases( (array) ( $g['release_dates'] ?? array() ) ),
		);
		$summary = wp_strip_all_tags( $g['summary'] ?? '' );
		$story   = wp_strip_all_tags( $g['storyline'] ?? '' );
		if ( $summary || $story ) {
			$fields['content'] = wpautop( esc_html( trim( $summary . ( $story ? "\n\n" . $story : '' ) ) ) );
			$fields['excerpt'] = wp_trim_words( $summary ?: $story, 55 );
		}
		foreach ( array( 'genres', 'game_modes' ) as $key ) {
			$names = array_values( array_filter( array_map( 'sanitize_text_field', array_column( (array) ( $g[ $key ] ?? array() ), 'name' ) ) ) );
			if ( $names ) {
				$fields[ $key ] = $names;
			}
		}
		foreach ( array(
			'developer' => 'developers',
			'publisher' => 'publishers',
		) as $flag => $key ) {
			$names = array();
			foreach ( (array) ( $g['involved_companies'] ?? array() ) as $credit ) {
				if ( ! empty( $credit[ $flag ] ) && ! empty( $credit['company']['name'] ) ) {
					$names[] = sanitize_text_field( $credit['company']['name'] );
				}
			}
			if ( $names ) {
				$fields[ $key ] = array_values( array_unique( $names ) );
			}
		}
		$exact = array_column( array_filter( $fields['release_dates'], static fn( $r ) => 'day' === $r['precision'] && $r['date'] ), 'date' );
		sort( $exact );
		if ( $exact ) {
			$fields['release_date'] = $exact[0];
		}
		$record = array(
			'type' => 'msc_game',
			'data' => $fields,
		);
		if ( ! GamePolicy::accepts( $record ) ) {
			throw new SourceError( 'This game has no supported Sony console release for this site. Import it on GameRevolution instead.' );
		}
		$image = null;
		$hash  = $g['cover']['image_id'] ?? '';
		if ( is_string( $hash ) && preg_match( '/^[A-Za-z0-9_-]{1,150}$/D', $hash ) ) {
			$image = array(
				'url'    => 'https://images.igdb.com/igdb/image/upload/t_cover_big_2x/' . $hash . '.jpg',
				'source' => $source + array( 'credit' => 'Cover artwork belongs to its respective rights holder.' ),
			);
		}
		return array(
			'provider'      => 'igdb',
			'id'            => $id,
			'type'          => 'msc_game',
			'fields'        => $fields,
			'external_ids'  => array( 'igdb' => $id ),
			'source'        => $source,
			'field_sources' => array(),
			'credits'       => array(),
			'image'         => $image,
			'warnings'      => array( 'Regional/platform dates are retained individually. Incomplete dates remain TBD and are not placed on a calendar day.' ),
			'details'       => array( 'updated_at' => (int) ( $g['updated_at'] ?? 0 ) ),
		);
	}
}
