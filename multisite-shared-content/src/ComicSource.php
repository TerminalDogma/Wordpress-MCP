<?php
namespace MSC;

/** Public GCD metadata API. Artwork rights are independent of its data licence. */
final class ComicSource {
    public static function id( string $value, string $kind = '' ): string {
        if ( preg_match( '/^[1-9][0-9]{0,9}$/D', $value ) ) { return $value; }
        $p = wp_parse_url( $value );
        if ( 'https' === ( $p['scheme'] ?? '' ) && 'www.comics.org' === ( $p['host'] ?? '' ) && empty( $p['user'] ) && empty( $p['port'] ) && preg_match( '#^/(?:api/)?(series|issue|publisher)/([1-9][0-9]{0,9})/$#D', $p['path'] ?? '', $m ) && ( ! $kind || $kind === $m[1] ) ) { return $m[2]; }
        throw new SourceError( 'Choose a valid Grand Comics Database record.' );
    }
    private static function get( string $kind, string $id ): array {
        $id = self::id( $id, $kind ); $r = OpenClient::get( 'gcd', $kind . '/' . $id . '/', array( 'format' => 'json' ), DAY_IN_SECONDS );
        if ( self::id( (string) ( $r['api_url'] ?? '' ), $kind ) !== $id ) { throw new SourceError( 'GCD returned a different record.', 502 ); } return $r;
    }
    public static function search( string $type, string $query, int $offset ): array {
        if ( ! in_array( $type, array( 'msc_comic', 'msc_comic_issue' ), true ) ) { throw new SourceError( 'GCD imports Comic Books and Comic Issues. Create Story Arcs in the editor.' ); }
        $path = 'series/name/' . rawurlencode( $query ) . '/';
        if ( 'msc_comic_issue' === $type ) {
            if ( ! preg_match( '/^(.+?)\s*#\s*(.{1,80})$/u', $query, $m ) ) { throw new SourceError( 'Search issues as Series title #number, for example Batman #404, or open Import issues from a Comic Book.' ); }
            $path = 'series/name/' . rawurlencode( trim( $m[1] ) ) . '/issue/' . rawurlencode( $m[2] ) . '/';
        }
        $data = OpenClient::get( 'gcd', $path, array( 'format' => 'json', 'page' => max( 1, $offset ) ) ); $items = array();
        foreach ( array_slice( $data['results'] ?? array(), 0, 50 ) as $r ) {
            $id = self::id( (string) ( $r['api_url'] ?? '' ), 'msc_comic' === $type ? 'series' : 'issue' );
            $title = 'msc_comic' === $type ? ( $r['name'] ?? '' ) : ( ( $r['series_name'] ?? '' ) . ' #' . ( $r['number'] ?? $r['descriptor'] ?? '' ) );
            if ( ! trim( $title ) ) { continue; }
            $items[] = array( 'provider' => 'gcd', 'id' => $id, 'title' => sanitize_text_field( $title ), 'description' => sanitize_text_field( 'msc_comic' === $type ? implode( ' – ', array_filter( array( $r['year_began'] ?? '', $r['year_ended'] ?? '' ) ) ) . ' · ' . ( $r['language'] ?? '' ) : ( $r['publication_date'] ?? '' ) . ' ' . ( $r['variant_name'] ?? '' ) ) );
        }
        $next = null; if ( ! empty( $data['next'] ) ) { parse_str( wp_parse_url( $data['next'], PHP_URL_QUERY ) ?: '', $query_args ); $next = isset( $query_args['page'] ) && ctype_digit( (string) $query_args['page'] ) ? (int) $query_args['page'] : null; }
        return array( 'items' => $items, 'next' => $next && $next <= 200 ? $next : null );
    }
    public static function issues( string $series ): array {
        $data = self::get( 'series', $series ); $out = array();
        foreach ( $data['active_issues'] ?? array() as $index => $url ) { $out[] = array( 'id' => self::id( $url, 'issue' ), 'title' => sanitize_text_field( $data['issue_descriptors'][ $index ] ?? 'Issue' ) ); }
        return $out;
    }
    private static function names( array $stories, array $keys, bool $cover = false ): array {
        $out = array(); foreach ( $stories as $story ) { if ( $cover !== ( 'cover' === ( $story['type'] ?? '' ) ) ) { continue; } foreach ( $keys as $key ) { foreach ( explode( ';', (string) ( $story[ $key ] ?? '' ) ) as $name ) { $name = trim( preg_replace( '/\s*\(credited[^)]*\)/i', '', $name ) ); if ( '' !== $name && ! preg_match( '/^(?:none|unknown|\?)(?:\s|$)/i', $name ) ) { $out[] = sanitize_text_field( $name ); } } } }
        return array_slice( array_values( array_unique( $out ) ), 0, 100 );
    }
    public static function details( string $id, string $type ): array {
        $issue = 'msc_comic_issue' === $type; if ( ! $issue && 'msc_comic' !== $type ) { throw new SourceError( 'Unsupported comic import type.' ); }
        $d = self::get( $issue ? 'issue' : 'series', $id ); $series_id = $issue ? self::id( $d['series'] ?? '', 'series' ) : self::id( $id ); $series = $issue ? self::get( 'series', $series_id ) : $d;
        $fields = array( 'title' => sanitize_text_field( $series['name'] ?? '' ) ); if ( ! $fields['title'] ) { throw new SourceError( 'GCD did not return a series title.', 502 ); }
        if ( ! empty( $series['publisher'] ) ) { $p = self::get( 'publisher', self::id( $series['publisher'], 'publisher' ) ); if ( ! empty( $p['name'] ) ) { $fields['publisher'] = sanitize_text_field( $p['name'] ); } }
        $notes = trim( wp_strip_all_tags( $d['notes'] ?? '' ) );
        if ( $issue ) {
            $fields['issue_number'] = sanitize_text_field( (string) ( $d['number'] ?? '' ) );
            $fields['title'] .= ' #' . $fields['issue_number'];
            foreach ( array( 'volume', 'price', 'isbn', 'barcode' ) as $key ) { if ( '' !== trim( (string) ( $d[ $key ] ?? '' ) ) ) { $fields[ $key ] = sanitize_text_field( $d[ $key ] ); } }
            if ( ! empty( $d['variant_name'] ) ) { $fields['variant'] = sanitize_text_field( $d['variant_name'] ); $fields['title'] .= ' (' . $fields['variant'] . ')'; }
            if ( ! empty( $d['publication_date'] ) ) { $fields['cover_date'] = sanitize_text_field( $d['publication_date'] ); }
            $date = $d['on_sale_date'] ?? ''; if ( ReleaseCalendar::date( $date ) ) { $fields['release_date'] = $date; }
            if ( isset( $d['page_count'] ) && is_numeric( $d['page_count'] ) && $d['page_count'] >= 0 ) { $fields['page_count'] = (float) $d['page_count']; }
            $stories = array_slice( (array) ( $d['story_set'] ?? array() ), 0, 200 );
            foreach ( array( 'writers' => array( 'script' ), 'artists' => array( 'pencils', 'inks' ), 'cover_artists' => array( 'pencils', 'inks' ) ) as $field => $keys ) { $names = self::names( $stories, $keys, 'cover_artists' === $field ); if ( $names ) { $fields[ $field ] = $names; } }
            $synopses = array(); foreach ( $stories as $story ) { if ( 'comic story' === ( $story['type'] ?? '' ) && ! empty( $story['synopsis'] ) ) { $synopses[] = trim( wp_strip_all_tags( $story['synopsis'] ) ); } }
            if ( $synopses ) { $notes = implode( "\n\n", array_slice( $synopses, 0, 20 ) ); }
        } else {
            foreach ( array( 'year_began' => 'start_year', 'year_ended' => 'end_year', 'publishing_format' => 'publication_format', 'country' => 'country', 'language' => 'language' ) as $from => $to ) { if ( isset( $d[ $from ] ) && '' !== $d[ $from ] ) { $fields[ $to ] = $d[ $from ]; } }
            $fields['issue_count'] = count( $d['active_issues'] ?? array() );
        }
        if ( $notes ) { $fields['content'] = wpautop( esc_html( mb_substr( $notes, 0, 20000 ) ) ); $fields['excerpt'] = wp_trim_words( $notes, 55 ); }
        $source = OpenSources::source( 'gcd', 'https://www.comics.org/' . ( $issue ? 'issue' : 'series' ) . '/' . self::id( $id ) . '/' );
        return array( 'provider' => 'gcd', 'id' => self::id( $id ), 'type' => $type, 'fields' => $fields, 'external_ids' => array( 'gcd' => self::id( $id ) ), 'source' => $source, 'field_sources' => array(), 'credits' => array(), 'image' => null, 'warnings' => array( 'GCD metadata is CC BY-SA 4.0. Cover-image rights are separate; use the image picker for your cover.', 'Arc membership and reading-order positions are editorial fields; the GCD API does not reliably provide them.' ), 'details' => array( 'parent_gcd' => $series_id ) );
    }
}
