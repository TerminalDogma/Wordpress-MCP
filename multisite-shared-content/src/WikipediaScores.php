<?php
namespace MSC;

/** Read Wikipedia's published reception data; never query paid score services. */
final class WikipediaScores {
    public static function title( $value ): string {
        if ( ! is_string( $value ) && null !== $value ) { throw new SourceError( 'Enter a Wikipedia article title or English Wikipedia URL.' ); }
        $value = trim( $value ?? '' ); if ( '' === $value ) { return ''; }
        if ( preg_match( '~^https?://~i', $value ) ) {
            $p = wp_parse_url( $value ); if ( ! $p || 'en.wikipedia.org' !== strtolower( $p['host'] ?? '' ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['port'] ) ) { throw new SourceError( 'Choose an English Wikipedia article URL.' ); }
            if ( str_starts_with( $p['path'] ?? '', '/wiki/' ) ) { $value = rawurldecode( substr( $p['path'], 6 ) ); }
            elseif ( '/w/index.php' === ( $p['path'] ?? '' ) ) { parse_str( $p['query'] ?? '', $q ); $value = $q['title'] ?? ''; }
            else { throw new SourceError( 'Choose a Wikipedia article page.' ); }
        }
        if ( ! is_string( $value ) ) { throw new SourceError( 'Invalid Wikipedia article title.' ); }
        $value = trim( str_replace( '_', ' ', sanitize_text_field( $value ) ) );
        if ( ! $value || mb_strlen( $value ) > 240 || preg_match( '/[|#\[\]{}<>\x00-\x1f]/u', $value ) || preg_match( '/^(?:Special|User(?: talk)?|Talk|Template|Category|File|Wikipedia|MediaWiki|Help|Portal|Draft|Module|Media):/i', $value ) ) { throw new SourceError( 'Choose a main Wikipedia article, up to 240 characters.' ); }
        return $value;
    }
    public static function search( string $title, string $type ): array {
        $kind = 'msc_game' === $type ? 'video game' : ( 'msc_movie' === $type ? 'film' : 'television' );
        $data = OpenClient::get( 'wikipedia', '', array( 'action' => 'query', 'list' => 'search', 'srsearch' => mb_substr( $title, 0, 160 ) . ' ' . $kind, 'srnamespace' => 0, 'srlimit' => 5, 'srprop' => 'snippet' ), 6 * HOUR_IN_SECONDS );
        $items = array(); foreach ( (array) ( $data['query']['search'] ?? array() ) as $row ) {
            if ( ! is_string( $row['title'] ?? null ) || str_ends_with( $row['title'], '(disambiguation)' ) ) { continue; }
            $items[] = array( 'title' => self::title( $row['title'] ), 'description' => mb_substr( sanitize_text_field( wp_strip_all_tags( $row['snippet'] ?? '' ) ), 0, 240 ) );
        }
        return $items;
    }
    private static function document( string $html ): \DOMDocument {
        $doc = new \DOMDocument(); $previous = libxml_use_internal_errors( true );
        try { $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING ); } finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
        return $doc;
    }
    private static function text( \DOMNode $node ): string {
        $copy = self::document( $node->ownerDocument->saveHTML( $node ) ); $xp = new \DOMXPath( $copy );
        foreach ( iterator_to_array( $xp->query( '//sup|//script|//style' ) ) as $remove ) { $remove->parentNode->removeChild( $remove ); }
        return trim( preg_replace( '/\s+/u', ' ', $copy->textContent ) );
    }
    private static function link( \DOMNode $fragment, \DOMDocument $article, string $provider ): string {
        $xp = new \DOMXPath( $fragment->ownerDocument );
        foreach ( $xp->query( './/a[@href]', $fragment ) as $anchor ) {
            $href = $anchor->getAttribute( 'href' ); $url = self::external( $provider, $href ); if ( $url ) { return $url; }
            $id = wp_parse_url( $href, PHP_URL_FRAGMENT ); if ( ! is_string( $id ) || ! $id ) { continue; } $reference = $article->getElementById( rawurldecode( $id ) );
            if ( $reference ) { foreach ( ( new \DOMXPath( $article ) )->query( './/a[@href]', $reference ) as $source ) { $url = self::external( $provider, $source->getAttribute( 'href' ) ); if ( $url ) { return $url; } } }
        }
        return '';
    }
    private static function external( string $provider, string $url ): string { if ( str_starts_with( $url, '//' ) ) { $url = 'https:' . $url; } if ( str_starts_with( $url, 'http://' ) ) { $url = 'https://' . substr( $url, 7 ); } return ReviewScores::provider_url( $provider, $url ); }
    public static function platform( string $value ): string {
        $key = strtolower( preg_replace( '/[^a-z0-9]/i', '', $value ) );
        $aliases = array( 'pc' => 'PC', 'windows' => 'PC', 'microsoftwindows' => 'PC', 'ps5' => 'PlayStation 5', 'ps4' => 'PlayStation 4', 'ps3' => 'PlayStation 3', 'ps2' => 'PlayStation 2', 'ps' => 'PlayStation', 'ps1' => 'PlayStation', 'psp' => 'PSP', 'vita' => 'PS Vita', 'psvita' => 'PS Vita', 'xsxs' => 'Xbox Series X/S', 'xbsx' => 'Xbox Series X/S', 'xsx' => 'Xbox Series X/S', 'xboxseriesxs' => 'Xbox Series X/S', 'x360' => 'Xbox 360', 'xb360' => 'Xbox 360', 'xone' => 'Xbox One', 'xbone' => 'Xbox One', 'ns' => 'Nintendo Switch', 'nsw' => 'Nintendo Switch', 'switch' => 'Nintendo Switch' );
        return $aliases[ $key ] ?? sanitize_text_field( trim( $value ) );
    }
    public static function parse( string $html ): array {
        if ( strlen( $html ) > 5 * MB_IN_BYTES ) { throw new SourceError( 'The Wikipedia article is too large to parse.', 502 ); }
        $doc = self::document( $html ); $xp = new \DOMXPath( $doc ); $result = array( 'metacritic' => array(), 'opencritic' => array() ); $headers = array();
        foreach ( $xp->query( '//tr' ) as $row ) {
            $cells = $xp->query( './th|./td', $row ); if ( $cells->length < 2 ) { continue; } $label = self::text( $cells->item( 0 ) ); $table = $xp->query( 'ancestor::table[1]', $row )->item( 0 ); $table_key = $table ? $table->getNodePath() : '';
            if ( 'Aggregator' === $label ) { $headers[ $table_key ] = array(); foreach ( $cells as $cell ) { $headers[ $table_key ][] = self::text( $cell ); } continue; }
            $provider = array( 'metacritic' => 'metacritic', 'opencritic' => 'opencritic' )[ strtolower( trim( $label ) ) ] ?? ''; if ( ! $provider ) { continue; }
            for ( $i = 1; $i < $cells->length; ++$i ) {
                $inner = ''; foreach ( $cells->item( $i )->childNodes as $child ) { $inner .= $doc->saveHTML( $child ); }
                foreach ( preg_split( '~<br\s*/?>~i', $inner ) as $line ) {
                    $fragment = self::document( '<div>' . $line . '</div>' ); $root = ( new \DOMXPath( $fragment ) )->query( '//body/div' )->item( 0 ); if ( ! $root ) { continue; }
                    $text = self::text( $root ); $metric = 'average'; $platform = ''; $number = null;
                    if ( 'opencritic' === $provider && preg_match( '/^(\d+(?:\.\d+)?)\s*%\s*(?:of\s+(?:the\s+)?critics\s+)?recommend(?:ed|ation)?/i', $text, $match ) ) { $number = $match[1]; $metric = 'recommended'; }
                    elseif ( preg_match( '~^(?:\(([^)]+)\)\s*)?(\d+(?:\.\d+)?)\s*(?:/\s*100|out of\s*100)\b~i', $text, $match ) ) { $platform = self::platform( $match[1] ?? '' ); $number = $match[2]; }
                    if ( null === $number || null === ReviewScores::value( $number ) ) { continue; }
                    if ( ! $platform && $cells->length > 2 ) { $platform = self::platform( $headers[ $table_key ][ $i ] ?? '' ); }
                    $result[ $provider ][] = array( 'score' => ReviewScores::value( $number ), 'platform' => $platform, 'metric' => $metric, 'url' => self::link( $root, $doc, $provider ) );
                }
            }
        }
        foreach ( array( 'metacritic', 'opencritic' ) as $provider ) {
            if ( $result[ $provider ] ) { continue; }
            foreach ( $xp->query( '//p' ) as $paragraph ) {
                $text = self::text( $paragraph ); if ( ! str_contains( strtolower( $text ), $provider ) ) { continue; } $metric = 'average'; $score = null;
                if ( 'metacritic' === $provider && preg_match( '~\bMetacritic\b(?:(?!Rotten Tomatoes|OpenCritic).){0,240}?\b(?:score|rating|average)\b.{0,60}?(\d+(?:\.\d+)?)\s*(?:out of|/)\s*100\b~i', $text, $m ) ) { $score = $m[1]; }
                elseif ( 'opencritic' === $provider && preg_match( '~\bOpenCritic\b.{0,140}?(\d+(?:\.\d+)?)\s*%\s*(?:of\s+(?:the\s+)?critics\s+)?recommend~i', $text, $m ) ) { $score = $m[1]; $metric = 'recommended'; }
                elseif ( 'opencritic' === $provider && preg_match( '~\bOpenCritic\b.{0,140}?\b(?:average|score)\b.{0,40}?(\d+(?:\.\d+)?)\s*(?:out of|/)\s*100\b~i', $text, $m ) ) { $score = $m[1]; }
                if ( null !== $score && null !== ReviewScores::value( $score ) ) { $result[ $provider ][] = array( 'score' => ReviewScores::value( $score ), 'platform' => '', 'metric' => $metric, 'url' => self::link( $paragraph, $doc, $provider ) ); }
            }
        }
        foreach ( $result as &$options ) { $seen = array(); $options = array_values( array_filter( $options, static function ( $option ) use ( &$seen ) { $key = $option['score'] . '|' . $option['platform'] . '|' . $option['metric']; if ( isset( $seen[ $key ] ) ) { return false; } return $seen[ $key ] = true; } ) ); } unset( $options );
        return $result;
    }
    public static function article( string $title, array $record ): array {
        $data = OpenClient::get( 'wikipedia', '', array( 'action' => 'parse', 'page' => self::title( $title ), 'redirects' => 1, 'prop' => 'text|revid|categories' ), 6 * HOUR_IN_SECONDS ); $p = $data['parse'] ?? array();
        $title = self::title( $p['title'] ?? '' ); $revision = (int) ( $p['revid'] ?? 0 ); $html = $p['text'] ?? ''; if ( is_array( $html ) ) { $html = $html['*'] ?? ''; }
        if ( ! $revision || ! is_string( $html ) || ! $html ) { throw new SourceError( 'Wikipedia did not return a usable article revision.', 502 ); }
        foreach ( (array) ( $p['categories'] ?? array() ) as $category ) { if ( str_contains( strtolower( (string) ( $category['category'] ?? $category['*'] ?? '' ) ), 'disambiguation' ) ) { throw new SourceError( 'Choose a specific article rather than a disambiguation page.' ); } }
        $source = 'https://en.wikipedia.org/w/index.php?title=' . rawurlencode( str_replace( ' ', '_', $title ) ) . '&oldid=' . $revision; $parsed = self::parse( $html ); $choices = array();
        foreach ( array_intersect( ReviewScores::providers( $record['type'] ), array( 'metacritic', 'opencritic' ) ) as $provider ) {
            foreach ( $parsed[ $provider ] as $option ) {
                if ( 'msc_game' === $record['type'] && 'all' !== GamePolicy::mode() && $option['platform'] && ! GamePolicy::platform_allowed( 0, $option['platform'] ) ) { continue; }
                $values = array( $provider . '_score' => $option['score'], $provider . '_url' => $option['url'], $provider . '_data_source' => $source );
                if ( 'metacritic' === $provider ) { $values['metacritic_platform'] = $option['platform']; } else { $values['opencritic_metric'] = $option['metric']; }
                $choices[ $provider ][] = array( 'label' => ( $option['platform'] ? $option['platform'] . ' · ' : '' ) . Reviews::number( $option['score'] ) . ( 'recommended' === $option['metric'] ? '% critics recommend' : '/100 critic average' ), 'values' => $values );
            }
        }
        return array( 'title' => $title, 'source' => $source, 'choices' => $choices );
    }
}
