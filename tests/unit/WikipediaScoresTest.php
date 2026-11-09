<?php
/**
 * Score extraction from Wikipedia article HTML.
 *
 * @package MultisiteSharedContent
 */

use MSC\SourceError;
use MSC\WikipediaScores;
use PHPUnit\Framework\TestCase;

final class WikipediaScoresTest extends TestCase {

	public function test_reception_table_yields_each_platform_score(): void {
		$parsed = WikipediaScores::parse( (string) file_get_contents( dirname( __DIR__ ) . '/shared-content/wiki-scores-clair.html' ) );
		$this->assertSame( array( 92.0, 91.0, 91.0 ), array_column( $parsed['metacritic'], 'score' ) );
		$this->assertSame( array( 'PlayStation 5', 'PC', 'Xbox Series X/S' ), array_column( $parsed['metacritic'], 'platform' ) );
		$this->assertSame( 'recommended', $parsed['opencritic'][0]['metric'] );
	}

	public function test_prose_metacritic_score(): void {
		$parsed = WikipediaScores::parse( '<p>Metacritic assigned the game a weighted average score of 84 out of 100, based on 20 reviews.</p>' );
		$this->assertSame( 84.0, $parsed['metacritic'][0]['score'] );
	}

	public function test_invalid_values_are_not_invented(): void {
		foreach ( array( 'N/A', '111/100', '-1/100', '98% positive' ) as $text ) {
			$parsed = WikipediaScores::parse( '<table><tr><td>OpenCritic</td><td>' . $text . '</td></tr></table>' );
			$this->assertSame( array(), $parsed['opencritic'], $text );
		}
	}

	public function test_platform_aliases(): void {
		$this->assertSame( 'PlayStation 5', WikipediaScores::platform( 'PS5' ) );
		$this->assertSame( 'Xbox Series X/S', WikipediaScores::platform( 'XSXS' ) );
		$this->assertSame( 'PC', WikipediaScores::platform( 'Windows' ) );
	}

	public function test_title_accepts_article_urls_and_rejects_other_namespaces(): void {
		$this->assertSame( 'Clair Obscur: Expedition 33', WikipediaScores::title( 'https://en.wikipedia.org/wiki/Clair_Obscur:_Expedition_33#Reception' ) );
		foreach ( array( 'https://en.wikipedia.org.evil.test/wiki/Game', 'Template:Game', 'Game|other' ) as $bad ) {
			try {
				WikipediaScores::title( $bad );
				$this->fail( 'Accepted ' . $bad );
			} catch ( SourceError $e ) {
				$this->assertNotSame( '', $e->getMessage() );
			}
		}
	}
}
