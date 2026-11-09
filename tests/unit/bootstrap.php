<?php
/**
 * Bootstrap for fast unit tests of plugin code that does not need WordPress.
 *
 * Only pure helpers are tested here; everything that touches the database or
 * the network is covered by the Docker suite in tests/shared-content.
 *
 * @package MultisiteSharedContent
 */

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/' );
define( 'MSC_VERSION', '0.0.0-test' );
define( 'MB_IN_BYTES', 1024 * 1024 );

spl_autoload_register(
	static function ( string $name ): void {
		if ( str_starts_with( $name, 'MSC\\' ) ) {
			$file = dirname( __DIR__, 2 ) . '/multisite-shared-content/src/' . substr( $name, 4 ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	}
);

// Minimal versions of the WordPress functions these helpers call.
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}
