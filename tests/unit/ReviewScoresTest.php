<?php
/**
 * Aggregate score normalization and provider links.
 *
 * @package MultisiteSharedContent
 */

use MSC\ReviewScores;
use PHPUnit\Framework\TestCase;

final class ReviewScoresTest extends TestCase {

	public function test_value_rounds_to_one_decimal_within_zero_to_one_hundred(): void {
		$this->assertSame( 88.0, ReviewScores::value( '88' ) );
		$this->assertSame( 73.5, ReviewScores::value( 73.46 ) );
		$this->assertSame( 0.0, ReviewScores::value( 0 ) );
		$this->assertSame( 100.0, ReviewScores::value( 100 ) );
	}

	public function test_value_rejects_out_of_range_and_non_numeric(): void {
		foreach ( array( -1, 100.1, 'N/A', '', null, NAN, array() ) as $input ) {
			$this->assertNull( ReviewScores::value( $input ) );
		}
	}

	public function test_provider_url_accepts_only_https_provider_pages(): void {
		$this->assertSame( 'https://www.rottentomatoes.com/m/example', ReviewScores::provider_url( 'rotten_tomatoes', 'https://www.rottentomatoes.com/m/example' ) );
		$this->assertSame( 'https://www.metacritic.com/game/example/', ReviewScores::provider_url( 'metacritic', 'https://www.metacritic.com/game/example/' ) );
		$this->assertSame( '', ReviewScores::provider_url( 'metacritic', 'http://www.metacritic.com/game/example/' ) );
		$this->assertSame( '', ReviewScores::provider_url( 'metacritic', 'https://www.metacritic.com:8443/game/example/' ) );
		$this->assertSame( '', ReviewScores::provider_url( 'metacritic', 'https://metacritic.com.example.test/game/' ) );
		$this->assertSame( '', ReviewScores::provider_url( 'opencritic', 'https://www.metacritic.com/game/example/' ) );
		$this->assertSame( '', ReviewScores::provider_url( 'metacritic', array( 'https://www.metacritic.com/' ) ) );
	}
}
