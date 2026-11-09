<?php
/**
 * Trailer URL parsing and the privacy-friendly embed URLs it produces.
 *
 * @package MultisiteSharedContent
 */

use MSC\Trailers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrailersTest extends TestCase {

	public static function youtube_urls(): array {
		return array(
			array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			array( 'https://youtu.be/dQw4w9WgXcQ' ),
			array( 'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=10' ),
			array( 'https://www.youtube.com/embed/dQw4w9WgXcQ' ),
			array( 'https://www.youtube.com/shorts/dQw4w9WgXcQ/' ),
			array( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' ),
		);
	}

	#[DataProvider( 'youtube_urls' )]
	public function test_youtube_urls_use_the_nocookie_embed( string $url ): void {
		$this->assertSame(
			array(
				'url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0',
			),
			Trailers::video( $url )
		);
	}

	public function test_vimeo_urls_disable_tracking(): void {
		$this->assertSame(
			array(
				'url'   => 'https://vimeo.com/123456789',
				'embed' => 'https://player.vimeo.com/video/123456789?dnt=1',
			),
			Trailers::video( 'https://player.vimeo.com/video/123456789' )
		);
	}

	public static function rejected_urls(): array {
		return array(
			array( 'https://www.youtube.com/watch?v=short' ),
			array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ"onload' ),
			array( 'javascript:alert(1)' ),
			array( 'https://user:pass@www.youtube.com/watch?v=dQw4w9WgXcQ' ),
			array( 'https://www.youtube.com:8443/watch?v=dQw4w9WgXcQ' ),
			array( 'https://youtube.com.example.test/watch?v=dQw4w9WgXcQ' ),
			array( 'https://vimeo.com/abc' ),
		);
	}

	#[DataProvider( 'rejected_urls' )]
	public function test_other_urls_are_rejected( string $url ): void {
		$this->assertNull( Trailers::video( $url ) );
	}
}
