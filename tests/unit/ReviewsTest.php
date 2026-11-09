<?php
/**
 * Editorial review score rules.
 *
 * @package MultisiteSharedContent
 */

use MSC\Reviews;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewsTest extends TestCase {

	public static function valid_scores(): array {
		return array( array( 1, 1.0 ), array( '7.5', 7.5 ), array( 10, 10.0 ), array( 8.5, 8.5 ) );
	}

	#[DataProvider( 'valid_scores' )]
	public function test_score_accepts_half_points_from_one_to_ten( $input, float $expected ): void {
		$this->assertSame( $expected, Reviews::score( $input ) );
	}

	public static function invalid_scores(): array {
		return array( array( 0.5 ), array( 10.5 ), array( 7.3 ), array( 'abc' ), array( null ), array( INF ), array( array( 8 ) ) );
	}

	#[DataProvider( 'invalid_scores' )]
	public function test_score_rejects_other_values( $input ): void {
		$this->assertNull( Reviews::score( $input ) );
	}

	public function test_number_drops_a_trailing_zero(): void {
		$this->assertSame( '8', Reviews::number( 8.0 ) );
		$this->assertSame( '8.5', Reviews::number( 8.5 ) );
	}

	public function test_award_thresholds(): void {
		$this->assertSame( 'gold', Reviews::award( 9.5 ) );
		$this->assertSame( 'gold', Reviews::award( 10.0 ) );
		$this->assertSame( 'silver', Reviews::award( 9.0 ) );
		$this->assertSame( 'copper', Reviews::award( 8.5 ) );
		$this->assertSame( '', Reviews::award( 8.0 ) );
	}
}
