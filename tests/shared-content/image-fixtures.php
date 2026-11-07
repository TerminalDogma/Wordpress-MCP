<?php
/* Plugin Name: MSC URL image fixtures (disposable local tests only) */
if ( ! defined( 'MSC_IMAGE_TEST_FIXTURES' ) || ! MSC_IMAGE_TEST_FIXTURES || 'local' !== wp_get_environment_type() ) { return; }
final class MSC_Image_Fixtures {
    public static array $files = array();
    public static int $requests = 0;
    public static array $last = array();
    public static function http( $pre, array $args, string $url ) {
        if ( '93.184.216.34' !== wp_parse_url( $url, PHP_URL_HOST ) || ! str_starts_with( wp_parse_url( $url, PHP_URL_PATH ) ?? '', '/msc-url-fixture-' ) ) { return $pre; }
        ++self::$requests; self::$last = $args; $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( str_contains( $path, 'redirect-private' ) ) { return array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => 'https://127.0.0.1/private.png' ), 'body' => '' ); }
        if ( str_contains( $path, 'redirect-http' ) ) { return array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => 'http://93.184.216.34/msc-url-fixture-image.png' ), 'body' => '' ); }
        if ( str_contains( $path, 'redirect-loop' ) ) { return array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => '/msc-url-fixture-redirect-loop.png' ), 'body' => '' ); }
        if ( str_contains( $path, 'redirect-relative' ) ) { return array( 'response' => array( 'code' => 302 ), 'headers' => array( 'location' => '/msc-url-fixture-image.png' ), 'body' => '' ); }
        if ( str_contains( $path, 'offline' ) ) { return new WP_Error( 'offline', 'Fixture transport failure.' ); }
        $body = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
        if ( str_contains( $path, 'html' ) ) { $body = '<html>Search result page</html>'; }
        if ( str_contains( $path, 'svg' ) ) { $body = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'; }
        if ( str_contains( $path, 'oversize' ) ) { $body = str_repeat( 'x', 8 * MB_IN_BYTES + 1 ); }
        if ( ! empty( $args['stream'] ) ) { file_put_contents( $args['filename'], $body ); self::$files[] = $args['filename']; $body = ''; }
        return array( 'response' => array( 'code' => str_contains( $path, '404' ) ? 404 : 200 ), 'headers' => array( 'content-type' => 'image/png' ), 'body' => $body );
    }
}
add_filter( 'pre_http_request', array( MSC_Image_Fixtures::class, 'http' ), 10, 3 );
