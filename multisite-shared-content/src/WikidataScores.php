<?php
/**
 * Read critic aggregate scores from Wikidata review-score statements.
 *
 * Wikidata records each score as a "review score" (P444) statement with the
 * provider in "review score by" (P447), the measure in "determination method"
 * (P459), the date in "point in time" (P585) and, for games, the platform in
 * "platform" (P400). The data is CC0 and needs no API key.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class WikidataScores {
	/** Wikidata items for the score providers this plugin shows. */
	public const PROVIDERS = array(
		'Q105584'   => 'rotten_tomatoes',
		'Q150248'   => 'metacritic',
		'Q21039459' => 'opencritic',
	);
	/**
	 * Accepted measures per provider. A statement without a measure is accepted
	 * only in the value format listed in parse(); any other measure is skipped,
	 * so audience and user scores are never taken for critic scores.
	 */
	private const METHODS = array(
		'rotten_tomatoes' => array( 'Q108403393' ),               // Tomatometer score.
		'metacritic'      => array( 'Q106515043' ),               // Metascore.
		'opencritic'      => array( 'Q114712322', 'Q119576498' ), // Top Critic Average, Critics Recommend.
	);
	/** Provider page IDs on Wikidata, used for the "View source score" links. */
	private const PAGES = array(
		'rotten_tomatoes' => array( 'P1258', 'https://www.rottentomatoes.com/', '~^(?:m|tv)/[a-z0-9_/-]+$~D' ),
		'metacritic'      => array( 'P1712', 'https://www.metacritic.com/', '~^(?:movie|tv|game)/[a-z0-9_/-]+$~D' ),
		'opencritic'      => array( 'P2864', 'https://opencritic.com/game/', '~^[1-9][0-9]{0,8}$~D' ),
	);

	/** Normalize a Wikidata item ID or item URL; returns '' for anything else. */
	public static function id( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( preg_match( '~^https://(?:www\.|m\.)?wikidata\.org/wiki/(Q[1-9][0-9]{0,11})/?$~D', $value, $match ) ) {
			return $match[1];
		}
		return preg_match( '/^Q[1-9][0-9]{0,11}$/D', $value ) ? $value : '';
	}

	public static function url( string $id ): string {
		return 'https://www.wikidata.org/wiki/' . self::id( $id );
	}

	/**
	 * Score options found on an entity, per provider, best first.
	 *
	 * @param array $entity    A wbgetentities entity with claims.
	 * @param array $platforms Platform item ID => label, for game platforms.
	 * @return array<string, list<array{score: float, metric: string, platform: string, as_of: string, reviews: ?int}>>
	 */
	public static function parse( array $entity, array $platforms = array() ): array {
		$result = array_fill_keys( array_values( self::PROVIDERS ), array() );
		foreach ( (array) ( $entity['claims']['P444'] ?? array() ) as $order => $claim ) {
			if ( ! is_array( $claim ) || 'deprecated' === ( $claim['rank'] ?? '' ) ) {
				continue;
			}
			$value    = $claim['mainsnak']['datavalue']['value'] ?? null;
			$provider = self::PROVIDERS[ self::qualifier( $claim, 'P447' ) ] ?? '';
			if ( ! is_string( $value ) || ! $provider ) {
				continue;
			}
			$method = self::qualifier( $claim, 'P459' );
			if ( '' !== $method && ! in_array( $method, self::METHODS[ $provider ], true ) ) {
				continue;
			}
			$value = trim( $value );
			if ( preg_match( '/^(\d+(?:\.\d+)?)\s*%$/D', $value, $match ) ) {
				$format = 'percent';
			} elseif ( preg_match( '~^(\d+(?:\.\d+)?)\s*/\s*100$~D', $value, $match ) ) {
				$format = 'hundred';
			} else {
				continue;
			}
			$metric = 'average';
			if ( 'rotten_tomatoes' === $provider ) {
				if ( 'percent' !== $format ) {
					continue;
				}
			} elseif ( 'metacritic' === $provider ) {
				if ( 'hundred' !== $format ) {
					continue;
				}
			} elseif ( 'percent' === $format ) {
				$metric = 'recommended';
			}
			$score = ReviewScores::value( $match[1] );
			if ( null === $score ) {
				continue;
			}
			$time                  = self::qualifier_value( $claim, 'P585' );
			$reviews               = self::qualifier_value( $claim, 'P7887' );
			$platform              = self::qualifier( $claim, 'P400' );
			$result[ $provider ][] = array(
				'score'     => $score,
				'metric'    => $metric,
				'platform'  => '' !== $platform ? WikipediaScores::platform( (string) ( $platforms[ $platform ] ?? $platform ) ) : '',
				'as_of'     => is_array( $time ) && preg_match( '/^\+(\d{4}-\d{2}-\d{2})/', (string) ( $time['time'] ?? '' ), $date ) ? $date[1] : '',
				'reviews'   => is_array( $reviews ) && is_numeric( $reviews['amount'] ?? null ) ? (int) $reviews['amount'] : null,
				'preferred' => 'preferred' === ( $claim['rank'] ?? '' ),
				'order'     => (int) $order,
			);
		}
		foreach ( $result as &$options ) {
			// Preferred statements first, then the newest, then Wikidata's own order.
			usort( $options, static fn( $a, $b ) => array( $b['preferred'], $b['as_of'], $a['order'] ) <=> array( $a['preferred'], $a['as_of'], $b['order'] ) );
			$options = array_map(
				static function ( array $option ): array {
					unset( $option['preferred'], $option['order'] );
					return $option;
				},
				$options
			);
		}
		unset( $options );
		return $result;
	}

	/** Provider title-page URLs from the entity's provider IDs. */
	public static function links( array $entity ): array {
		$links = array();
		foreach ( self::PAGES as $provider => list( $property, $base, $pattern ) ) {
			$id = $entity['claims'][ $property ][0]['mainsnak']['datavalue']['value'] ?? null;
			if ( is_string( $id ) && preg_match( $pattern, $id ) ) {
				$links[ $provider . '_url' ] = $base . $id;
			}
		}
		return $links;
	}

	/** Find the Wikidata item for an IMDb title ID, if exactly one item has it. */
	public static function find_by_imdb( string $imdb ): string {
		if ( ! preg_match( '/^tt[0-9]{7,9}$/D', $imdb ) ) {
			return '';
		}
		$data = OpenClient::get(
			'wikidata',
			'',
			array(
				'action'      => 'query',
				'list'        => 'search',
				'srsearch'    => 'haswbstatement:P345=' . $imdb,
				'srnamespace' => 0,
				'srlimit'     => 2,
			),
			DAY_IN_SECONDS
		);
		$rows = (array) ( $data['query']['search'] ?? array() );
		return 1 === count( $rows ) ? self::id( $rows[0]['title'] ?? '' ) : '';
	}

	/**
	 * Score choices for the score editor, in the same shape as WikipediaScores::article().
	 *
	 * @return array{label: string, article: string, links: array, choices: array}
	 */
	public static function lookup( string $id, array $record ): array {
		$id     = self::id( $id );
		$entity = $id ? ( OpenClient::entities( array( $id ) )[ $id ] ?? array() ) : array();
		if ( ! $entity || isset( $entity['missing'] ) ) {
			throw new SourceError( 'Wikidata has no item ' . ( $id ? $id : 'with that ID' ) . '.', 404 );
		}
		$platform_ids = array();
		foreach ( (array) ( $entity['claims']['P444'] ?? array() ) as $claim ) {
			$platform = is_array( $claim ) ? self::qualifier( $claim, 'P400' ) : '';
			if ( '' !== $platform ) {
				$platform_ids[] = $platform;
			}
		}
		$platforms = $platform_ids ? OpenClient::labels( $platform_ids ) : array();
		$links     = self::links( $entity );
		$source    = self::url( $id );
		$choices   = array();
		foreach ( self::parse( $entity, $platforms ) as $provider => $options ) {
			if ( ! in_array( $provider, ReviewScores::providers( $record['type'] ), true ) ) {
				continue;
			}
			foreach ( $options as $option ) {
				if ( 'msc_game' === $record['type'] && 'all' !== GamePolicy::mode() && $option['platform'] && ! GamePolicy::platform_allowed( 0, $option['platform'] ) ) {
					continue;
				}
				$values = array(
					$provider . '_score'       => $option['score'],
					$provider . '_url'         => $links[ $provider . '_url' ] ?? '',
					$provider . '_data_source' => $source,
				);
				if ( 'metacritic' === $provider && 'msc_game' === $record['type'] ) {
					$values['metacritic_platform'] = $option['platform'];
				} elseif ( 'opencritic' === $provider ) {
					$values['opencritic_metric'] = $option['metric'];
				}
				$choices[ $provider ][] = array(
					'label'  => self::label( $provider, $option ),
					'values' => $values,
				);
			}
		}
		return array(
			'label'   => (string) ( $entity['labels']['en']['value'] ?? $entity['labels']['mul']['value'] ?? $id ),
			'article' => (string) ( $entity['sitelinks']['enwiki']['title'] ?? '' ),
			'links'   => $links,
			'choices' => $choices,
		);
	}

	private static function label( string $provider, array $option ): string {
		$score = Reviews::number( $option['score'] );
		$text  = 'rotten_tomatoes' === $provider ? $score . '% Tomatometer' : ( 'recommended' === $option['metric'] ? $score . '% critics recommend' : $score . '/100 critic average' );
		$parts = array_filter( array( $option['platform'], $text, $option['reviews'] ? $option['reviews'] . ' reviews' : '', $option['as_of'] ? 'as of ' . $option['as_of'] : '' ) );
		return implode( ' · ', $parts ) . ' (Wikidata)';
	}

	/** The item ID of a claim's first qualifier value, or ''. */
	private static function qualifier( array $claim, string $property ): string {
		$value = self::qualifier_value( $claim, $property );
		return is_array( $value ) && is_string( $value['id'] ?? null ) ? $value['id'] : '';
	}

	private static function qualifier_value( array $claim, string $property ) {
		return $claim['qualifiers'][ $property ][0]['datavalue']['value'] ?? null;
	}
}
