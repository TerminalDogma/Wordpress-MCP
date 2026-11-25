<?php
namespace MSC;

/** The public overview and the field that supplies it on older records. */
final class Overview {
	public static function keys( string $type ): array {
		return 'msc_movie' === $type ? array( 'editorial_synopsis', 'content', 'excerpt' ) : array( 'content', 'excerpt' );
	}

	public static function source( string $type, array $data ): string {
		foreach ( self::keys( $type ) as $key ) {
			if ( is_string( $data[ $key ] ?? null ) && '' !== trim( $data[ $key ] ) ) { return $key; }
		}
		return 'content';
	}

	public static function summary( array $record, int $words ): string {
		$data = $record['data'];
		$key = self::source( $record['type'], $data );
		$text = preg_replace( '~</(?:p|div|h[1-6]|li)>|<br\b[^>]*>~i', '$0 ', $data[ $key ] ?? '' );
		return wp_trim_words( Presentation::text( wp_strip_all_tags( strip_shortcodes( $text ) ) ), $words, '…' );
	}
}
