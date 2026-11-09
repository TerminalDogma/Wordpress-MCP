<?php
use MSC\{MediaImport, Media, Attribution};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local test network required.' );
}
define( 'MSC_IMAGE_TEST_FIXTURES', true );
require __DIR__ . '/image-fixtures.php';
function img_check( $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['img_checks'];
	WP_CLI::log( 'PASS: ' . $message );
}
function img_reject( callable $fn, string $message ): void {
	try {
		$fn();
	} catch ( Throwable $e ) {
		img_check( true, $message );
		return;
	}
	throw new RuntimeException( 'FAIL: expected image rejection: ' . $message );
}
$GLOBALS['img_checks'] = 0;
wp_set_current_user( 1 );
$created = array();
try {
	$ref       = MediaImport::import( 'https://93.184.216.34/msc-url-fixture-image.png', 'Fixture poster', 'https://93.184.216.34/image-source', 'Fixture photographer' );
	$created[] = $ref['attachment_id'];
	img_check( get_current_blog_id() === $ref['site_id'] && 'attachment' === get_post_type( $ref['attachment_id'] ) && is_file( get_attached_file( $ref['attachment_id'] ) ), 'A URL image becomes a local attachment with portable site ownership' );
	img_check( Media::sanitize( $ref ) === $ref && Media::url( $ref ) && str_contains( Media::preview( $ref ), '<img' ), 'Imported images use the existing preview and cross-site media API' );
	$html = Attribution::media_html( $ref );
	img_check( str_contains( $html, 'Fixture photographer' ) && str_contains( $html, 'https://93.184.216.34/image-source' ), 'Optional source and credit remain available for the footer' );
	img_check( true === MSC_Image_Fixtures::$last['sslverify'] && 0 === MSC_Image_Fixtures::$last['redirection'] && MediaImport::MAX_BYTES + 1 === MSC_Image_Fixtures::$last['limit_response_size'], 'Image requests verify TLS and bound bytes and redirect handling' );
	foreach ( array( 'http://93.184.216.34/image.png', 'https://127.0.0.1/a.png', 'https://localhost/a.png', 'https://169.254.169.254/a.png', 'https://[::1]/a.png', 'https://user:password@93.184.216.34/a.png', 'https://93.184.216.34:8443/a.png', 'file:///tmp/a.png', 'data:image/png;base64,AAA' ) as $url ) {
		$before = MSC_Image_Fixtures::$requests;
		img_reject( static fn() => MediaImport::import( $url ), 'Unsafe, private or credential-bearing image URL is rejected' );
		img_check( $before === MSC_Image_Fixtures::$requests, 'Invalid URLs trigger no image request' );
	}
	foreach ( array( 'html', 'svg', 'oversize', '404', 'offline', 'redirect-private', 'redirect-http', 'redirect-loop' ) as $kind ) {
		img_reject( static fn() => MediaImport::import( 'https://93.184.216.34/msc-url-fixture-' . $kind . '.png' ), 'Image failure is handled safely: ' . $kind );
	}
	$ref       = MediaImport::import( 'https://93.184.216.34/msc-url-fixture-redirect-relative.png' );
	$created[] = $ref['attachment_id'];
	img_check( is_file( get_attached_file( $ref['attachment_id'] ) ), 'A bounded public HTTPS relative redirect imports successfully' );
	img_check( ! array_filter( MSC_Image_Fixtures::$files, 'is_file' ), 'Temporary downloads are removed after success and failure' );
	wp_set_current_user( 0 );
	img_reject( static fn() => MediaImport::import( 'https://93.184.216.34/msc-url-fixture-image.png' ), 'Anonymous users cannot create image attachments' );
} finally {
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		wp_delete_attachment( $id, true );
	}
}
WP_CLI::success( $GLOBALS['img_checks'] . ' URL image, permission, validation, redirect and cleanup checks passed.' );
