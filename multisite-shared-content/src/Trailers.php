<?php
namespace MSC;

/** Portable trailer URLs, rendered without remote discovery requests or pasted HTML. */
final class Trailers {
	public static function boot(): void {
		add_action(
			'admin_enqueue_scripts',
			static function () {
				if ( ! MediaImport::allowed() ) {
					return;
				}
				wp_enqueue_script( 'msc-trailer-editor', plugins_url( 'assets/trailers-editor.js', MSC_FILE ), array(), MSC_VERSION, true );
				wp_enqueue_style( 'msc-trailer-editor', plugins_url( 'assets/trailers-editor.css', MSC_FILE ), array(), MSC_VERSION );
				wp_localize_script(
					'msc-trailer-editor',
					'mscTrailers',
					array(
						'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
						'nonce'      => wp_create_nonce( 'msc_trailers_' . get_current_blog_id() ),
						'configured' => ImageSearch::configured(),
					)
				);
			}
		);
		add_action(
			'wp_ajax_msc_search_trailers',
			static function () {
				check_ajax_referer( 'msc_trailers_' . get_current_blog_id(), 'nonce' );
				try {
					if ( ! is_string( $_POST['query'] ?? null ) || ! is_scalar( $_POST['page'] ?? 1 ) || ! preg_match( '/^[1-9][0-9]?$/D', (string) ( $_POST['page'] ?? 1 ) ) ) {
						throw new SourceError( 'Invalid trailer search request.' );
					}
					wp_send_json_success( ImageSearch::search( wp_unslash( $_POST['query'] ), (int) ( $_POST['page'] ?? 1 ), 'videos' ) );
				} catch ( \Throwable $e ) {
					wp_send_json_error( array( 'message' => $e instanceof SourceError ? $e->getMessage() : 'Trailer search failed. Please retry.' ), $e instanceof SourceError ? $e->status : 502 );
				}
			}
		);
	}
	public static function results( array $rows ): array {
		$items = array();
		foreach ( array_slice( $rows, 0, ImageSearch::PAGE_SIZE ) as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['link'] ?? null ) ) {
				continue;
			}
			$video = self::video( $row['link'] );
			if ( ! $video || ! str_starts_with( $video['url'], 'https://www.youtube.com/watch?v=' ) || isset( $items[ $video['url'] ] ) ) {
				continue;
			}
			$text                   = static fn( $v ) => is_string( $v ) ? mb_substr( sanitize_text_field( $v ), 0, 300 ) : '';
			$items[ $video['url'] ] = $video + array(
				'title'     => $text( $row['title'] ?? '' ) ?: 'YouTube trailer',
				'channel'   => $text( $row['channel'] ?? '' ),
				'duration'  => $text( $row['duration'] ?? '' ),
				'thumbnail' => 'https://i.ytimg.com/vi/' . substr( $video['url'], -11 ) . '/mqdefault.jpg',
			);
		}
		return array_values( $items );
	}
	public static function video( string $url ): ?array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return null;
		}
		$host = strtolower( $parts['host'] ?? '' );
		$path = $parts['path'] ?? '';
		$id   = '';
		if ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be' ), true ) ) {
			if ( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) ) {
				$id = trim( $path, '/' );
			} elseif ( '/watch' === $path ) {
				parse_str( $parts['query'] ?? '', $query );
				$id = $query['v'] ?? '';
			} elseif ( preg_match( '~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~D', $path, $match ) ) {
				$id = $match[1];
			}
			if ( ! is_string( $id ) || ! preg_match( '/^[A-Za-z0-9_-]{11}$/D', $id ) ) {
				return null;
			}
			return array(
				'url'   => 'https://www.youtube.com/watch?v=' . $id,
				'embed' => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0',
			);
		}
		if ( in_array( $host, array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ), true ) && preg_match( '~^/(?:video/)?([0-9]{6,12})/?$~D', $path, $match ) ) {
			return array(
				'url'   => 'https://vimeo.com/' . $match[1],
				'embed' => 'https://player.vimeo.com/video/' . $match[1] . '?dnt=1',
			);
		}
		return null;
	}
	public static function sanitize( $value ): string {
		if ( ! is_string( $value ) && null !== $value ) {
			throw new \InvalidArgumentException( 'Enter trailer URLs, one per line.' );
		}
		$urls = array();
		foreach ( preg_split( '/\R/u', trim( $value ?? '' ) ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$video = self::video( trim( $line ) );
			if ( ! $video ) {
				throw new \InvalidArgumentException( 'Trailers require a YouTube or public Vimeo video URL, one per line.' );
			}
			$urls[ $video['url'] ] = true;
			if ( count( $urls ) > 12 ) {
				throw new \InvalidArgumentException( 'Add at most 12 trailers.' );
			}
		}
		return implode( "\n", array_keys( $urls ) );
	}
	public static function render( array $record ): void {
		if ( ! in_array( $record['type'], array( 'msc_movie', 'msc_show', 'msc_game' ), true ) ) {
			return;
		}
		$videos = array();
		foreach ( preg_split( '/\R/u', (string) ( $record['data']['trailers'] ?? '' ) ) as $line ) {
			$v = self::video( trim( $line ) );
			if ( $v ) {
				$videos[ $v['url'] ] = $v;
			}
			if ( count( $videos ) >= 12 ) {
				break;
			}
		}
		if ( ! $videos ) {
			return;
		}
		$total = count( $videos );
		echo '<section class="trailer-section" data-trailer-carousel role="region" aria-roledescription="carousel" aria-label="Trailers"><h2>Trailers</h2><div class="trailer-stage">';
		foreach ( array_values( $videos ) as $index => $video ) {
			$number = $index + 1;
			echo '<div class="trailer-slide" role="group" aria-roledescription="slide" aria-label="' . esc_attr( $number . ' of ' . $total ) . '"' . ( $index ? ' hidden' : '' ) . '><iframe ' . ( $index ? '' : 'src="' . esc_url( $video['embed'] ) . '" ' ) . 'data-src="' . esc_url( $video['embed'] ) . '" title="' . esc_attr( ( $record['data']['title'] ?? '' ) . ' — Trailer ' . $number ) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
		}
		echo '</div><div class="trailer-controls" hidden><button type="button" data-trailer-prev aria-label="Previous trailer" disabled>Previous</button><span data-trailer-status aria-live="polite" aria-atomic="true">Trailer 1 of ' . (int) $total . '</span><button type="button" data-trailer-next aria-label="Next trailer">Next</button></div><noscript><ul class="trailer-links">';
		foreach ( array_values( $videos ) as $index => $video ) {
			echo '<li><a href="' . esc_url( $video['url'] ) . '">Watch trailer ' . (int) ( $index + 1 ) . '</a></li>';
		}
		echo '</ul></noscript></section>';
	}
}
