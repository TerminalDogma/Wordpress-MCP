<?php
/* Plugin Name: MSC URL image fixtures (disposable local tests only) */
if ( ! defined( 'MSC_IMAGE_TEST_FIXTURES' ) || ! MSC_IMAGE_TEST_FIXTURES || 'local' !== wp_get_environment_type() ) { return; }
final class MSC_Image_Fixtures {
    public static array $files = array();
    public static int $requests = 0;
    public static array $last = array();
    public static int $searches = 0;
    public static array $last_search = array();
    public static function http( $pre, array $args, string $url ) {
        if ( 'google.serper.dev' === wp_parse_url( $url, PHP_URL_HOST ) ) {
            ++self::$searches; self::$last_search = $args; $q = json_decode( $args['body'], true ); $query = $q['q'] ?? ''; $page = (int) ( $q['page'] ?? 1 );
            if ( str_contains( $query, 'quota' ) ) { return array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '5' ), 'body' => '' ); }
            if ( str_contains( $query, 'badkey' ) ) { return array( 'response' => array( 'code' => 403 ), 'headers' => array(), 'body' => 'private key must never reach the browser' ); }
            if ( str_contains( $query, 'offline' ) ) { return new WP_Error( 'offline', 'Private provider transport details' ); }
            if ( str_contains( $query, 'malformed' ) ) { return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'not json' ); }
            $items = array(); $count = str_contains( $query, 'empty' ) ? 0 : ( 1 === $page ? 10 : 2 );
            for ( $i = 1; $i <= $count; ++$i ) { $position = ( $page - 1 ) * 10 + $i; $items[] = array( 'title' => 'Poster result ' . $position, 'imageUrl' => 'https://93.184.216.34/msc-url-fixture-image.png?result=' . $position, 'thumbnailUrl' => 'https://encrypted-tbn0.gstatic.com/images?fixture=' . $position, 'imageWidth' => 600, 'imageHeight' => 900, 'source' => 'Fixture source', 'link' => 'http://93.184.216.34/image-source-' . $position ); }
            return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array( 'images' => $items ) ) );
        }
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
