<?php
/** Plugin Name: MSC Open Import Test Fixtures (disposable local installations only) */
if ( ! defined( 'MSC_OPEN_TEST_FIXTURES' ) || ! MSC_OPEN_TEST_FIXTURES || wp_get_environment_type() !== 'local' ) { return; }
final class MSC_Open_Fixtures {
    public static int $requests = 0;
    public static bool $bad_image = false;
    public static function seed(): int { return (int) get_network_option( get_current_network_id(), 'msc_open_fixture_seed', 710000000 ); }
    public static function entity( string $id, string $name, array $claims = array(), string $article = '' ): array {
        $mapped = array(); foreach ( $claims as $property => $values ) { foreach ( $values as $value ) { $mapped[ $property ][] = array( 'rank' => 'normal', 'mainsnak' => array( 'snaktype' => 'value', 'datavalue' => array( 'value' => $value ) ) ); } }
        return array( 'id' => $id, 'labels' => array( 'en' => array( 'value' => $name ) ), 'descriptions' => array( 'en' => array( 'value' => 'Provider contract fixture' ) ), 'claims' => $mapped, 'sitelinks' => $article ? array( 'enwiki' => array( 'title' => $article ) ) : array() );
    }
    public static function date( string $date, int $precision = 11 ): array { return array( 'time' => '+' . $date . 'T00:00:00Z', 'precision' => $precision, 'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727' ); }
    public static function entities(): array {
        $s = self::seed(); $p = 'Q' . ( $s + 102 ); $other = 'Q' . ( $s + 103 ); $movie = 'Q' . ( $s + 101 );
        return array(
            $movie => self::entity( $movie, 'Open Film ' . $s, array( 'P31' => array( array( 'id' => 'Q24869' ) ), 'P345' => array( 'tt' . ( $s + 101 ) ), 'P495' => array( array( 'id' => 'Q30' ) ), 'P364' => array( array( 'id' => 'Q1860' ) ), 'P136' => array( array( 'id' => 'Q130232' ) ), 'P2047' => array( array( 'amount' => '+7200', 'unit' => 'http://www.wikidata.org/entity/Q11574' ) ), 'P577' => array( self::date( '2001-02-03' ) ), 'P161' => array( array( 'id' => $p ), array( 'id' => $other ) ), 'P57' => array( array( 'id' => $p ) ), 'P18' => array( 'Open CC BY-SA.png' ) ), 'Open Film' ),
            $p => self::entity( $p, 'Alex Sharedname', array( 'P31' => array( array( 'id' => 'Q5' ) ), 'P345' => array( 'nm' . ( $s + 102 ) ), 'P569' => array( self::date( '1980-04-05' ) ), 'P106' => array( array( 'id' => 'Q33999' ) ), 'P27' => array( array( 'id' => 'Q30' ) ), 'P18' => array( 'Open CC BY-SA.png' ) ), 'Alex Sharedname' ),
            $other => self::entity( $other, 'Alex Sharedname', array( 'P31' => array( array( 'id' => 'Q5' ) ), 'P569' => array( self::date( '1970-00-00', 9 ) ), 'P18' => array( 'Open NC.png' ) ) ),
            'Q24869' => self::entity( 'Q24869', 'feature film', array( 'P279' => array( array( 'id' => 'Q11424' ) ) ) ),
            'Q11424' => self::entity( 'Q11424', 'film' ), 'Q5' => self::entity( 'Q5', 'human' ),
            'Q30' => self::entity( 'Q30', 'United States' ), 'Q1860' => self::entity( 'Q1860', 'English' ), 'Q33999' => self::entity( 'Q33999', 'Actor' ), 'Q130232' => self::entity( 'Q130232', 'Drama' ),
        );
    }
    public static function show(): array {
        $s = self::seed(); return array( 'id' => $s + 1, 'name' => 'Open Show ' . $s, 'summary' => '<p>A commercially reusable test show.</p><script>bad()</script>', 'premiered' => '2020-01-01', 'ended' => null, 'language' => 'English', 'genres' => array( 'Drama' ), 'status' => 'Running', 'network' => array( 'name' => 'Test Network', 'country' => array( 'name' => 'United States', 'timezone' => 'America/New_York' ) ), 'webChannel' => null, 'externals' => array( 'imdb' => 'tt' . ( $s + 1 ) ), 'image' => array( 'original' => 'https://static.tvmaze.com/not-cleared.jpg' ) );
    }
    public static function person(): array { $s = self::seed(); return array( 'id' => $s + 21, 'name' => 'Alex Sharedname', 'birthday' => '1975-06-07', 'country' => array( 'name' => 'Canada' ), 'image' => array( 'original' => 'https://static.tvmaze.com/not-cleared-person.jpg' ) ); }
    public static function episodes(): array {
        $s = self::seed(); $base = array( 'summary' => '<p>Episode-specific story.</p>', 'runtime' => 42, 'airtime' => '22:00', 'airstamp' => '2020-01-02T03:00:00+00:00', 'type' => 'regular', '_embedded' => array( 'show' => self::show() ) );
        return array(
            array( 'id' => $s + 12, 'name' => 'Second Episode', 'season' => 1, 'number' => 2, 'airdate' => '2020-01-08' ) + $base,
            array( 'id' => $s + 11, 'name' => 'Pilot', 'season' => 1, 'number' => 1, 'airdate' => '2020-01-01' ) + $base,
            array( 'id' => $s + 13, 'name' => 'Unnumbered Special', 'season' => 1, 'number' => null, 'airdate' => '', 'runtime' => null, 'airtime' => '' ) + $base,
            array( 'id' => $s + 15, 'name' => 'Migration Episode', 'season' => 2, 'number' => 1, 'airdate' => '2021-01-01' ) + $base,
            array( 'id' => $s + 14, 'name' => 'Zero Special', 'season' => 0, 'number' => 0, 'airdate' => '2019-12-25', 'runtime' => 0, 'type' => 'significant_special' ) + $base,
        );
    }
    public static function http( $pre, array $args, string $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST ); $path = wp_parse_url( $url, PHP_URL_PATH ); parse_str( wp_parse_url( $url, PHP_URL_QUERY ) ?? '', $q ); $s = self::seed();
        if ( ! in_array( $host, array( 'api.tvmaze.com', 'www.wikidata.org', 'en.wikipedia.org', 'commons.wikimedia.org', 'upload.wikimedia.org' ), true ) ) { return $pre; }
        ++self::$requests; $data = null;
        if ( 'quota' === ( $q['q'] ?? '' ) || 'quota' === ( $q['search'] ?? '' ) ) { return array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '20' ), 'body' => '' ); }
        if ( 'offline' === ( $q['q'] ?? '' ) ) { return new WP_Error( 'offline', 'Test transport failure' ); }
        if ( 'malformed' === ( $q['q'] ?? '' ) ) { return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => 'not json' ); }
        if ( 'api.tvmaze.com' === $host ) {
            if ( '/search/shows' === $path ) { $data = array( array( 'show' => self::show() ) ); }
            elseif ( '/search/people' === $path ) { $data = array( array( 'person' => self::person() ) ); }
            elseif ( '/shows/' . ( $s + 1 ) === $path ) { $data = self::show(); }
            elseif ( '/people/' . ( $s + 21 ) === $path ) { $data = self::person(); }
            elseif ( '/shows/' . ( $s + 1 ) . '/episodes' === $path ) { $data = self::episodes(); }
            elseif ( '/shows/' . ( $s + 1 ) . '/seasons' === $path ) { $data = array( array( 'id' => $s + 31, 'number' => 1 ) ); }
            elseif ( preg_match( '~^/episodes/([0-9]+)$~D', $path, $m ) ) { foreach ( self::episodes() as $ep ) { if ( (string) $ep['id'] === $m[1] ) { $data = $ep; } } }
            elseif ( str_ends_with( $path, '/cast' ) || str_ends_with( $path, '/guestcast' ) ) { $data = array( array( 'person' => self::person(), 'character' => array( 'name' => 'Guest' ) ) ); }
            elseif ( str_ends_with( $path, '/crew' ) || str_ends_with( $path, '/guestcrew' ) ) { $data = array( array( 'person' => self::person(), 'type' => 'Director' ) ); }
        }
        elseif ( 'www.wikidata.org' === $host ) {
            if ( 'wbsearchentities' === ( $q['action'] ?? '' ) ) { $data = array( 'search' => array( array( 'id' => 'Q' . ( $s + 101 ) ), array( 'id' => 'Q' . ( $s + 102 ) ), array( 'id' => 'Q' . ( $s + 103 ) ) ) ); }
            elseif ( 'wbgetentities' === ( $q['action'] ?? '' ) ) { $all = self::entities(); $entities = array(); foreach ( explode( '|', $q['ids'] ) as $id ) { $entities[ $id ] = $all[ $id ] ?? array( 'id' => $id, 'missing' => '' ); } $data = array( 'entities' => $entities ); }
        }
        elseif ( 'en.wikipedia.org' === $host ) { $data = array( 'query' => array( 'pages' => array( array( 'pageid' => 1234567, 'title' => $q['titles'], 'extract' => 'Biography or film introduction for ' . $q['titles'] . '. Adapted with attribution.' ) ) ) ); }
        elseif ( 'commons.wikimedia.org' === $host ) {
            $license = str_contains( $q['titles'], ' NC' ) ? 'by-nc/4.0' : ( str_contains( $q['titles'], ' ND' ) ? 'by-nd/4.0' : 'by-sa/4.0' );
            $meta = array( 'Artist' => array( 'value' => '<a href="https://example.test/artist">Fixture Photographer</a>' ), 'LicenseShortName' => array( 'value' => strtoupper( str_replace( '/', ' ', $license ) ) ), 'LicenseUrl' => array( 'value' => 'https://creativecommons.org/licenses/' . $license . '/' ), 'Restrictions' => array( 'value' => str_contains( $q['titles'], 'Restricted' ) ? 'Personality rights' : '' ) );
            $data = array( 'query' => array( 'pages' => array( array( 'imageinfo' => array( array( 'url' => 'https://upload.wikimedia.org/msc-open-fixture.png', 'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Open_CC_BY-SA.png', 'mime' => 'image/png', 'width' => 1, 'height' => 1, 'extmetadata' => $meta ) ) ) ) ) );
        }
        elseif ( 'upload.wikimedia.org' === $host ) {
            $body = self::$bad_image ? 'not an image' : base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
            if ( ! empty( $args['stream'] ) ) { file_put_contents( $args['filename'], $body ); $body = ''; }
            return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'image/png' ), 'body' => $body );
        }
        return array( 'response' => array( 'code' => null === $data ? 404 : 200 ), 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $data ) );
    }
}
add_filter( 'pre_http_request', array( MSC_Open_Fixtures::class, 'http' ), 10, 3 );
