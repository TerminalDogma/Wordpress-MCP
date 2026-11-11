<?php
/** Deliberately synthetic metadata and PNGs, enabled only in disposable integration tests. */
if ( 'local' !== wp_get_environment_type() || '1' !== getenv( 'MSC_DISPOSABLE_TESTS' ) ) { throw new RuntimeException( 'Disposable tests required.' ); }
final class MSC_Training_Fixtures {
    public static bool $bad_image = false;
    public static bool $missing_cast = false;
    public static function cache( string $provider, string $path, array $params, array $data ): void {
        $roots = array( 'wikipedia' => 'https://en.wikipedia.org/w/api.php', 'tvmaze' => 'https://api.tvmaze.com/' );
        if ( 'wikipedia' === $provider ) { $params += array( 'format' => 'json', 'formatversion' => 2, 'maxlag' => 5 ); }
        set_site_transient( 'msc_open_' . hash( 'sha256', add_query_arg( $params, $roots[ $provider ] . $path ) ), $data, HOUR_IN_SECONDS );
    }
    public static function seed( array $row ): void {
        $claim = static fn( $id ) => array( array( 'mainsnak' => array( 'snaktype' => 'value', 'datavalue' => array( 'value' => array( 'id' => $id ) ) ), 'rank' => 'normal' ) );
        $id = $row['id']; $tv_id = '99' . substr( substr( $id, 1 ), -8 ); $article = 'MSC training fixture ' . $id;
        $entity = array( 'id' => $id, 'labels' => array( 'en' => array( 'value' => $row['fields']['title'] ) ), 'sitelinks' => array( 'enwiki' => array( 'title' => $article ) ), 'claims' => array( 'P57' => $claim( 'Q990000002' ), 'P161' => $claim( 'Q990000001' ), 'P272' => $claim( 'Q990000003' ) ) );
        if ( self::$missing_cast ) { unset( $entity['claims']['P161'] ); }
        set_site_transient( 'msc_open_entity_' . $id, $entity, HOUR_IN_SECONDS );
        foreach ( array( 'Q990000001' => 'Fixture lead performer', 'Q990000002' => 'Fixture director and writer', 'Q990000003' => 'Fixture production company' ) as $person => $name ) { set_site_transient( 'msc_open_entity_' . $person, array( 'id' => $person, 'labels' => array( 'en' => array( 'value' => $name ) ), 'claims' => array( 'P31' => $claim( 'Q5' ) ) ), HOUR_IN_SECONDS ); }
        self::cache( 'wikipedia', '', array( 'action' => 'query', 'titles' => $article, 'prop' => 'extracts|pageimages', 'exintro' => 1, 'explaintext' => 1, 'piprop' => 'original', 'pilicense' => 'any', 'redirects' => 1 ), array( 'query' => array( 'pages' => array( array( 'pageid' => 990000, 'extract' => str_repeat( 'This is a synthetic, fully described catalog integration fixture with a verified source identity, lead performer, director and production company. ', 2 ), 'original' => array( 'source' => 'https://static.tvmaze.com/uploads/images/msc-training-fixture.png' ) ) ) ) ) );
        if ( ! empty( $row['fields']['external_ids']['imdb'] ) ) {
            $imdb = $row['fields']['external_ids']['imdb'];
            if ( MSC\OmdbClient::key() ) { $params = array( 'i' => $imdb, 'plot' => 'full' ); set_site_transient( 'msc_omdb_' . hash( 'sha256', MSC\OmdbClient::key() . ':' . wp_json_encode( $params ) ), array( 'Response' => 'True', 'Title' => $row['fields']['title'], 'imdbID' => $imdb, 'Type' => 'msc_movie' === $row['type'] ? 'movie' : 'series', 'Poster' => '', 'Metascore' => '82', 'Actors' => '', 'Director' => '', 'Writer' => '' ), HOUR_IN_SECONDS ); }
            if ( 'msc_show' === $row['type'] ) { self::cache( 'tvmaze', 'lookup/shows', array( 'imdb' => $imdb ), array( 'id' => (int) $tv_id, 'externals' => array( 'imdb' => $imdb ) ) ); }
        }
        $tv = array( 'id' => (int) $tv_id, 'name' => $row['fields']['title'], 'summary' => '<p>' . str_repeat( 'A fully described television fixture following the cast through a series of documented adventures. ', 3 ) . '</p>', 'genres' => array( 'Drama' ), 'premiered' => $row['fields']['premiere_date'] ?? '2001-01-01', 'status' => 'Ended', 'image' => array( 'original' => 'https://static.tvmaze.com/uploads/images/msc-training-fixture.png' ) );
        self::cache( 'tvmaze', 'shows/' . $tv_id, array( 'embed' => 'seasons' ), $tv ); self::cache( 'tvmaze', 'shows/' . $tv_id, array(), $tv );
        foreach ( array( 'seasons' => array( array( 'id' => 1 ) ), 'cast' => array(), 'crew' => array(), 'images' => array() ) as $part => $data ) { self::cache( 'tvmaze', 'shows/' . $tv_id . '/' . $part, array(), $data ); }
    }
}
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    if ( 'https://static.tvmaze.com/uploads/images/msc-training-fixture.png' !== $url ) { return $pre; }
    $body = MSC_Training_Fixtures::$bad_image ? '<html>Broken image</html>' : base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' );
    if ( ! empty( $args['stream'] ) ) { file_put_contents( $args['filename'], $body ); $body = ''; }
    return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'image/png' ), 'body' => $body );
}, 10, 3 );
