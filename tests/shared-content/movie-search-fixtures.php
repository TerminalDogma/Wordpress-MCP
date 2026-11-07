<?php
/** Plugin Name: MSC Movie Search Fixtures (disposable local installations only) */
if ( ! defined( 'MSC_MOVIE_SEARCH_FIXTURES' ) || ! MSC_MOVIE_SEARCH_FIXTURES || 'local' !== wp_get_environment_type() ) { return; }
final class MSC_Movie_Search_Fixtures {
    public static int $requests = 0;
    public static array $calls = array();
    public static function seed(): int { return (int) get_network_option( get_current_network_id(), 'msc_movie_search_fixture_seed', 930000000 ); }
    private static function entity( string $id, string $name, string $property = '', array $ids = array() ): array {
        $claims = array(); foreach ( $ids as $other ) { $value = array( 'id' => $other ); $claims[ $property ][] = array( 'rank' => 'normal', 'mainsnak' => array( 'snaktype' => 'value', 'datavalue' => array( 'value' => $value ) ) ); }
        return array( 'id' => $id, 'labels' => array( 'en' => array( 'value' => $name ) ), 'descriptions' => array( 'en' => array( 'value' => 'Movie search regression fixture' ) ), 'claims' => $claims, 'sitelinks' => array() );
    }
    public static function http( $pre, array $args, string $url ) {
        if ( 'www.wikidata.org' !== wp_parse_url( $url, PHP_URL_HOST ) ) { return $pre; }
        parse_str( wp_parse_url( $url, PHP_URL_QUERY ) ?: '', $q ); ++self::$requests; self::$calls[] = $q;
        if ( str_contains( $q['search'] ?? '', 'quota' ) ) { return array( 'response' => array( 'code' => 429 ), 'headers' => array( 'retry-after' => '10' ), 'body' => '' ); }
        $seed = self::seed(); $data = array();
        if ( 'wbsearchentities' === ( $q['action'] ?? '' ) ) {
            $block = str_contains( $q['search'] ?? '', 'deep' ) ? 1 : ( str_contains( $q['search'] ?? '', 'mixed' ) ? 2 : ( str_contains( $q['search'] ?? '', 'missing' ) ? 3 : 0 ) );
            $rows = array(); for ( $i = 0; $i < 12; ++$i ) { $rows[] = array( 'id' => 'Q' . ( $seed + $block * 1000 + $i ) ); } $data = array( 'search' => $rows, 'search-continue' => 12 );
        } elseif ( 'wbgetentities' === ( $q['action'] ?? '' ) ) {
            $entities = array(); foreach ( explode( '|', $q['ids'] ?? '' ) as $id ) {
                $offset = (int) substr( $id, 1 ) - $seed; $block = intdiv( max( 0, $offset ), 1000 ); $n = $offset - $block * 1000; $base = $seed + $block * 1000;
                if ( 'Q5' === $id ) { $e = self::entity( $id, 'human' ); }
                elseif ( $offset < 0 || $n >= 999 ) { $e = array( 'id' => $id, 'missing' => '' ); }
                elseif ( $n < 12 ) { $class = 'Q' . ( $base + 100 + $n ); if ( 3 === $block || 2 === $block && 11 === $n ) { $class = 'Q' . ( $base + 999 ); } elseif ( 2 === $block && 9 === $n ) { $class = 'Q5'; } elseif ( 2 === $block && 10 === $n ) { $class = 'Q' . ( $base + 500 ); } $e = self::entity( $id, 'Fixture Movie ' . ( $n + 1 ), 'P31', array( $class ) ); }
                elseif ( $n >= 100 && $n < 112 ) { $e = self::entity( $id, 'Fixture Film Class ' . $n, 'P279', array( 1 === $block ? 'Q' . ( $base + 200 + $n - 100 ) : 'Q11424' ) ); }
                elseif ( $n >= 200 && $n < 212 ) { $e = self::entity( $id, 'Fixture Parent Class ' . $n, 'P279', array( 'Q11424' ) ); }
                elseif ( 500 === $n || 501 === $n ) { $e = self::entity( $id, 'Cyclic class', 'P279', array( 'Q' . ( $base + ( 500 === $n ? 501 : 500 ) ) ) ); }
                else { $e = array( 'id' => $id, 'missing' => '' ); }
                if ( 0 === $block && 0 === $n && ! isset( $e['missing'] ) ) { $e['labels'] = array( 'mul' => array( 'language' => 'mul', 'value' => 'Fixture Movie 1' ) ); }
                if ( isset( $e['labels'] ) ) { $all_labels = $e['labels']; $e['labels'] = array_intersect_key( $all_labels, array_flip( explode( '|', $q['languages'] ?? 'en' ) ) ); if ( ! empty( $q['languagefallback'] ) && ! isset( $e['labels']['en'] ) && isset( $all_labels['mul'] ) ) { $e['labels']['en'] = $all_labels['mul'] + array( 'for-language' => 'en' ); } }
                if ( 'claims' === ( $q['props'] ?? '' ) && ! isset( $e['missing'] ) ) { $e = array_intersect_key( $e, array_flip( array( 'id', 'claims' ) ) ); } $entities[ $id ] = $e;
            } $data = array( 'entities' => $entities );
        }
        return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $data ) );
    }
}
add_filter( 'pre_http_request', array( MSC_Movie_Search_Fixtures::class, 'http' ), 10, 3 );
