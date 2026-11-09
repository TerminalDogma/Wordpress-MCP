<?php
namespace MSC;

/** Shared import checks across providers and credit roles, including unpublished People. */
final class PersonIdentity {
	private static ?array $catalogue = null;
	public static function name( string $name ): string {
		$name = html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = strtr(
			$name,
			array(
				'’' => "'",
				'‘' => "'",
				'ʼ' => "'",
			)
		);
		return mb_strtolower( trim( preg_replace( '/[\s\p{Z}\x{200B}]+/u', ' ', remove_accents( $name ) ) ), 'UTF-8' );
	}
	public static function catalogue(): array {
		if ( null !== self::$catalogue ) {
			return self::$catalogue;
		}
		global $wpdb;
		$rows   = $wpdb->get_results( 'SELECT id,uuid,state,version,JSON_UNQUOTE(JSON_EXTRACT(data,\'$.title\')) AS title,JSON_EXTRACT(data,\'$.external_ids\') AS external_ids,JSON_UNQUOTE(JSON_EXTRACT(data,\'$.birth_date\')) AS birth_date,JSON_EXTRACT(data,\'$.professions\') AS professions FROM ' . Database::table( 'records' ) . " WHERE type='msc_person' ORDER BY id", ARRAY_A );
		$result = array();
		foreach ( $rows as $row ) {
			$result[ $row['uuid'] ] = array(
				'uuid'    => $row['uuid'],
				'id'      => $row['id'],
				'state'   => $row['state'],
				'version' => $row['version'],
				'type'    => 'msc_person',
				'data'    => array(
					'title'        => $row['title'] ?? '',
					'external_ids' => json_decode( $row['external_ids'] ?? '{}', true ) ?: array(),
					'birth_date'   => $row['birth_date'] ?? '',
					'professions'  => json_decode( $row['professions'] ?? '[]', true ) ?: array(),
				),
			);
		}
		return $result;
	}
	public static function locked( callable $callback ) {
		return Database::lock(
			'person:identity',
			static function () use ( $callback ) {
				$previous        = self::$catalogue;
				self::$catalogue = null;
				self::$catalogue = self::catalogue();
				try {
					return $callback();
				} finally {
					self::$catalogue = $previous;
				}
			}
		);
	}
	public static function remember( array $record ): array {
		if ( null !== self::$catalogue ) {
			self::$catalogue[ $record['uuid'] ] = $record;
		}
		return $record;
	}
	public static function credit_ids( array $credit ): array {
		return (array) ( $credit['external_ids'] ?? array() ) + array( $credit['provider'] => $credit['id'] );
	}
	private static function conflicts( array $a, array $b ): bool {
		foreach ( array( 'wikidata', 'tvmaze', 'imdb' ) as $key ) {
			if ( ! empty( $a[ $key ] ) && ! empty( $b[ $key ] ) && (string) $a[ $key ] !== (string) $b[ $key ] ) {
				return true;
			}
		}
		return false;
	}
	public static function assess( string $name, array $ids = array(), string $birth = '', ?array $catalogue = null ): array {
		$strong = $possible = array();
		$key    = self::name( $name );
		foreach ( $catalogue ?? self::catalogue() as $r ) {
			$other  = (array) ( $r['data']['external_ids'] ?? array() );
			$shared = false;
			foreach ( array( 'wikidata', 'tvmaze', 'imdb' ) as $provider ) {
				if ( ! empty( $ids[ $provider ] ) && isset( $other[ $provider ] ) && (string) $ids[ $provider ] === (string) $other[ $provider ] ) {
					$shared = true;
				}
			}
			if ( $shared ) {
				if ( self::conflicts( $ids, $other ) ) {
					throw new SourceError( 'A Person has conflicting source identifiers. Review Duplicate People before importing.', 409 );
				}
				$strong[ $r['uuid'] ] = $r;
				continue;
			}
			if ( '' === $key || self::name( $r['data']['title'] ?? '' ) !== $key || self::conflicts( $ids, $other ) ) {
				continue;
			}
			$born = (string) ( $r['data']['birth_date'] ?? '' );
			if ( $birth && $born && $birth !== $born ) {
				continue;
			}
			if ( $birth && $birth === $born ) {
				$strong[ $r['uuid'] ] = $r;
			} else {
				$possible[ $r['uuid'] ] = $r;
			}
		}
		if ( count( $strong ) > 1 ) {
			throw new SourceError( 'Multiple People match this identity. Review Duplicate People before importing.', 409 );
		}
		return array(
			'strong'   => $strong,
			'possible' => $possible,
		);
	}
	public static function review( array $credits ): array {
		$catalogue = self::catalogue();
		$result    = array();
		foreach ( $credits as $key => $c ) {
			$match              = self::assess( $c['name'], isset( $c['provider'] ) ? self::credit_ids( $c ) : array(), $c['fields']['birth_date'] ?? '', $catalogue );
			$match['suggested'] = $match['strong'] ? (string) array_key_first( $match['strong'] ) : ( $match['possible'] ? '' : 'new' );
			$result[ $key ]     = $match;
		}
		return $result;
	}
	/** Null means creation is allowed. Names alone require an explicit reuse/distinct choice. */
	public static function resolve( string $name, array $ids, string $birth, string $choice ): ?array {
		$match = self::assess( $name, $ids, $birth );
		if ( $match['strong'] ) {
			$r = reset( $match['strong'] );
			if ( ! in_array( $choice, array( '', 'auto', 'new', $r['uuid'] ), true ) ) {
				throw new SourceError( 'This source identity already belongs to a Person. Reuse that Person.', 409 );
			}
		} elseif ( preg_match( '/^[a-f0-9-]{36}$/D', $choice ) ) {
			$r = Plugin::store()->get( $choice );
		} else {
			if ( $match['possible'] && 'new_distinct' !== $choice ) {
				throw new SourceError( 'A Person named ' . $name . ' may already exist, including drafts. Choose the existing Person or explicitly create a distinct person in a fresh preview.', 409 );
			}
			if ( ! in_array( $choice, array( 'auto', 'new', 'new_distinct' ), true ) ) {
				throw new SourceError( 'Review the Person link for ' . $name . '.', 409 );
			}
			return null;
		}
		if ( ! $r || 'msc_person' !== $r['type'] || 'active' !== $r['state'] || self::conflicts( $ids, (array) ( $r['data']['external_ids'] ?? array() ) ) ) {
			throw new SourceError( 'The selected Person is unavailable or identifies someone else. Review their source IDs.', 409 );
		}
		return Plugin::store()->get( $r['uuid'] );
	}
	public static function professions( array $r, array $roles ): array {
		$labels = array(
			'actors'        => 'Actor',
			'directors'     => 'Director',
			'screenwriters' => 'Writer',
			'producers'     => 'Producer',
			'creators'      => 'Creator',
		);
		return array_values( array_unique( array_merge( (array) ( $r['data']['professions'] ?? array() ), array_values( array_intersect_key( $labels, array_flip( $roles ) ) ) ) ) );
	}
	public static function label( array $r ): string {
		$details = array_filter( array( implode( ', ', (array) ( $r['data']['professions'] ?? array() ) ), $r['data']['birth_date'] ?? '', implode( ', ', array_map( static fn( $key, $id ) => $key . ':' . $id, array_keys( (array) ( $r['data']['external_ids'] ?? array() ) ), array_values( (array) ( $r['data']['external_ids'] ?? array() ) ) ) ), 'active' !== $r['state'] ? $r['state'] : '' ) );
		return ( $r['data']['title'] ?: '(Untitled Person)' ) . ( $details ? ' — ' . implode( ' · ', $details ) : '' ) . ' [' . substr( $r['uuid'], 0, 8 ) . ']';
	}
}
