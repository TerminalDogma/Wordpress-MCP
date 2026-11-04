<?php
namespace MSC;

/** Provider adapters return facts and provenance, never studio-image licence guesses. */
final class OpenSources {
    public static function source( string $provider, string $url ): array {
        $license = 'wikidata' === $provider ? 'CC0 1.0' : 'CC BY-SA 4.0';
        return array( 'provider' => $provider, 'url' => $url, 'license' => $license, 'license_url' => 'wikidata' === $provider ? 'https://creativecommons.org/publicdomain/zero/1.0/' : 'https://creativecommons.org/licenses/by-sa/4.0/', 'attribution' => array( 'tvmaze' => 'TVmaze contributors', 'wikidata' => 'Wikidata contributors', 'wikipedia' => 'Wikipedia contributors' )[ $provider ], 'changes' => 'Selected, formatted and adapted for this catalog.' );
    }
    public static function claims( array $entity, string $property ): array {
        $claims = array_values( array_filter( (array) ( $entity['claims'][ $property ] ?? array() ), static fn( $c ) => 'deprecated' !== ( $c['rank'] ?? '' ) && 'value' === ( $c['mainsnak']['snaktype'] ?? '' ) ) );
        $preferred = array_filter( $claims, static fn( $c ) => 'preferred' === ( $c['rank'] ?? '' ) );
        return array_values( array_map( static fn( $c ) => $c['mainsnak']['datavalue']['value'] ?? null, $preferred ?: $claims ) );
    }
    private static function ids( array $entity, string $property ): array { return array_values( array_unique( array_filter( array_column( self::claims( $entity, $property ), 'id' ) ) ) ); }
    private static function label( array $entity ): string { return sanitize_text_field( $entity['labels']['en']['value'] ?? '' ); }
    private static function exact_date( $value ): string {
        if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) { return ''; }
        $d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ); return $d && $d->format( 'Y-m-d' ) === $value ? $value : '';
    }
    private static function wiki_date( array $value ): string {
        if ( 11 !== (int) ( $value['precision'] ?? 0 ) || ! str_ends_with( $value['calendarmodel'] ?? '', '/Q1985727' ) ) { return ''; }
        return self::exact_date( substr( ltrim( $value['time'] ?? '', '+' ), 0, 10 ) );
    }
    private static function kind( array $entity, string $type ): bool {
        $ids = self::ids( $entity, 'P31' );
        if ( 'msc_person' === $type ) { return in_array( 'Q5', $ids, true ); }
        $seen = array();
        for ( $depth = 0; $depth < 5 && $ids; ++$depth ) {
            if ( in_array( 'Q11424', $ids, true ) ) { return true; }
            $ids = array_values( array_diff( $ids, $seen ) ); if ( ! $ids || count( $ids ) > 50 ) { break; }
            $seen = array_merge( $seen, $ids ); $next = array();
            foreach ( OpenClient::entities( $ids ) as $class ) { $next = array_merge( $next, self::ids( $class, 'P279' ) ); }
            $ids = array_values( array_unique( $next ) );
        }
        return false;
    }
    public static function search( string $type, string $query, string $provider = '', int $offset = 0 ): array {
        $query = trim( sanitize_text_field( $query ) );
        if ( '' === $query || strlen( $query ) > 160 || ! in_array( $type, array( 'msc_movie', 'msc_show', 'msc_person', 'msc_episode' ), true ) || $offset < 0 || $offset > 200 ) { throw new SourceError( 'Enter a title or person name (up to 160 characters).' ); }
        if ( in_array( $type, array( 'msc_show', 'msc_episode' ), true ) || 'msc_person' === $type && 'tvmaze' === $provider ) {
            $people = 'msc_person' === $type; $rows = OpenClient::get( 'tvmaze', $people ? 'search/people' : 'search/shows', array( 'q' => $query ) ); $result = array();
            foreach ( array_slice( $rows, 0, 30 ) as $row ) { $item = $row[ $people ? 'person' : 'show' ] ?? array(); if ( empty( $item['id'] ) || '' === ( $item['name'] ?? '' ) ) { continue; } $result[] = array( 'provider' => 'tvmaze', 'id' => (string) $item['id'], 'title' => sanitize_text_field( $item['name'] ), 'description' => $people ? ( $item['country']['name'] ?? '' ) : implode( ' · ', array_filter( array( $item['premiered'] ?? '', $item['network']['name'] ?? $item['webChannel']['name'] ?? '', $item['language'] ?? '' ) ) ) ); }
            return array( 'items' => $result, 'next' => null );
        }
        $response = OpenClient::get( 'wikidata', '', array( 'action' => 'wbsearchentities', 'search' => $query, 'language' => 'en', 'uselang' => 'en', 'type' => 'item', 'limit' => 12, 'continue' => $offset ) );
        $entities = OpenClient::entities( array_column( $response['search'] ?? array(), 'id' ) ); $result = array();
        foreach ( $response['search'] ?? array() as $row ) {
            $entity = $entities[ $row['id'] ] ?? array(); if ( ! self::kind( $entity, $type ) ) { continue; }
            $result[] = array( 'provider' => 'wikidata', 'id' => $row['id'], 'title' => self::label( $entity ), 'description' => sanitize_text_field( $entity['descriptions']['en']['value'] ?? '' ) );
        }
        return array( 'items' => $result, 'next' => isset( $response['search-continue'] ) ? (int) $response['search-continue'] : null );
    }
    public static function show( string $id ): array {
        $data = OpenClient::get( 'tvmaze', 'shows/' . OpenClient::numeric( $id ) );
        if ( (string) ( $data['id'] ?? '' ) !== $id || '' === ( $data['name'] ?? '' ) ) { throw new SourceError( 'TVmaze returned a different show.', 502 ); } return $data;
    }
    public static function episodes( string $show ): array {
        self::show( $show ); $rows = OpenClient::get( 'tvmaze', 'shows/' . OpenClient::numeric( $show ) . '/episodes', array( 'specials' => 1 ) );
        $result = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['id'], $row['name'] ) ) { continue; }
            $result[] = array( 'id' => (string) $row['id'], 'title' => sanitize_text_field( $row['name'] ), 'season' => isset( $row['season'] ) ? (int) $row['season'] : null, 'number' => isset( $row['number'] ) ? (int) $row['number'] : null, 'date' => self::exact_date( $row['airdate'] ?? '' ), 'type' => sanitize_text_field( $row['type'] ?? '' ) );
        }
        usort( $result, static fn( $a, $b ) => array( $a['season'] ?? PHP_INT_MAX, $a['number'] ?? PHP_INT_MAX, $a['date'], (int) $a['id'] ) <=> array( $b['season'] ?? PHP_INT_MAX, $b['number'] ?? PHP_INT_MAX, $b['date'], (int) $b['id'] ) );
        return $result;
    }
    private static function tv_fields( array $d, string $type, array $show = array() ): array {
        $fields = array( 'title' => sanitize_text_field( $d['name'] ?? '' ) );
        $summary = wp_kses_post( $d['summary'] ?? '' ); if ( '' !== trim( wp_strip_all_tags( $summary ) ) ) { $fields['content'] = wpautop( $summary ); $fields['excerpt'] = wp_trim_words( wp_strip_all_tags( $summary ), 55 ); }
        if ( 'msc_person' === $type ) {
            $date = self::exact_date( $d['birthday'] ?? '' ); if ( $date ) { $fields['birth_date'] = $date; }
            if ( ! empty( $d['country']['name'] ) ) { $fields['country'] = sanitize_text_field( $d['country']['name'] ); }
            return $fields;
        }
        $owner = $show ?: $d;
        foreach ( array( 'network' => 'network', 'webChannel' => 'streaming_service' ) as $key => $field ) { if ( ! empty( $owner[ $key ]['name'] ) ) { $fields[ $field ] = sanitize_text_field( $owner[ $key ]['name'] ); } }
        if ( 'msc_show' === $type ) {
            foreach ( array( 'language', 'status' ) as $key ) { if ( ! empty( $d[ $key ] ) ) { $fields[ $key ] = sanitize_text_field( $d[ $key ] ); } }
            if ( ! empty( $d['genres'] ) ) { $fields['genres'] = array_map( 'sanitize_text_field', $d['genres'] ); }
            $country = $d['network']['country']['name'] ?? $d['webChannel']['country']['name'] ?? ''; if ( $country ) { $fields['country'] = sanitize_text_field( $country ); }
            foreach ( array( 'premiered' => 'premiere_date', 'ended' => 'finale_date' ) as $key => $field ) { $date = self::exact_date( $d[ $key ] ?? '' ); if ( $date ) { $fields[ $field ] = $date; } }
            if ( ! empty( $d['officialSite'] ) ) { $fields['official_website'] = esc_url_raw( $d['officialSite'], array( 'https', 'http' ) ); }
        } else {
            foreach ( array( 'season' => 'season_number', 'number' => 'episode_number' ) as $key => $field ) { if ( isset( $d[ $key ] ) && is_int( $d[ $key ] ) && $d[ $key ] >= 0 ) { $fields[ $field ] = $d[ $key ]; } }
            $date = self::exact_date( $d['airdate'] ?? '' ); if ( $date ) { $fields['release_date'] = $date; }
            if ( preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $d['airtime'] ?? '' ) ) { $fields['air_time'] = $d['airtime']; }
            $zone = $owner['network']['country']['timezone'] ?? $owner['webChannel']['country']['timezone'] ?? '';
            if ( in_array( $zone, \DateTimeZone::listIdentifiers(), true ) ) { $fields['time_zone'] = $zone; }
            if ( isset( $d['runtime'] ) && is_numeric( $d['runtime'] ) && $d['runtime'] >= 0 ) { $fields['runtime'] = 0 + $d['runtime']; }
        }
        return $fields;
    }
    private static function tv_url( string $type, string $id ): string {
        return 'https://www.tvmaze.com/' . array( 'msc_show' => 'shows', 'msc_episode' => 'episodes', 'msc_person' => 'people' )[ $type ] . '/' . $id;
    }
    public static function tv( string $id, string $type ): array {
        OpenClient::numeric( $id ); $show = array(); $credits = array();
        if ( 'msc_show' === $type ) { $d = self::show( $id ); }
        elseif ( 'msc_person' === $type ) { $d = OpenClient::get( 'tvmaze', 'people/' . $id ); }
        elseif ( 'msc_episode' === $type ) { $d = OpenClient::get( 'tvmaze', 'episodes/' . $id, array( 'embed' => 'show' ) ); $show = $d['_embedded']['show'] ?? array(); if ( empty( $show['id'] ) ) { throw new SourceError( 'TVmaze did not identify this episode’s show.', 502 ); } }
        else { throw new SourceError( 'TVmaze does not supply Movies.' ); }
        if ( (string) ( $d['id'] ?? '' ) !== $id || '' === ( $d['name'] ?? '' ) ) { throw new SourceError( 'The source returned a different record.', 502 ); }
        $fields = self::tv_fields( $d, $type, $show ); $ids = array( 'tvmaze' => $id );
        if ( preg_match( '/^tt[0-9]{7,12}$/D', $d['externals']['imdb'] ?? '' ) ) { $ids['imdb'] = $d['externals']['imdb']; }
        $url = self::tv_url( $type, $id ); $source = self::source( 'tvmaze', $url ); $warnings = array();
        if ( in_array( $type, array( 'msc_show', 'msc_episode' ), true ) ) {
            // The episode list remains separate: searching a show must never import a whole series.
            if ( 'msc_show' === $type ) { $seasons = OpenClient::get( 'tvmaze', 'shows/' . $id . '/seasons' ); $fields['number_of_seasons'] = count( $seasons ); }
            $root = 'msc_show' === $type ? 'shows/' : 'episodes/';
            $cast = OpenClient::get( 'tvmaze', $root . $id . ( 'msc_show' === $type ? '/cast' : '/guestcast' ) ); $crew = OpenClient::get( 'tvmaze', $root . $id . ( 'msc_show' === $type ? '/crew' : '/guestcrew' ) );
            foreach ( array_merge( array_slice( $cast, 0, 30 ), array_slice( $crew, 0, 30 ) ) as $credit ) {
                $p = $credit['person'] ?? array(); $role = isset( $credit['character'] ) ? 'actors' : ( array( 'Director' => 'directors', 'Writer' => 'screenwriters', 'Creator' => 'creators', 'Producer' => 'producers', 'Executive Producer' => 'producers' )[ $credit['type'] ?? '' ] ?? '' );
                if ( ! $role || empty( $p['id'] ) || empty( $p['name'] ) ) { continue; }
                $key = 'tvmaze:' . $p['id']; $credits[ $key ] = array( 'provider' => 'tvmaze', 'id' => (string) $p['id'], 'name' => sanitize_text_field( $p['name'] ), 'fields' => self::tv_fields( $p, 'msc_person' ), 'source' => self::source( 'tvmaze', self::tv_url( 'msc_person', (string) $p['id'] ) ), 'roles' => array_values( array_unique( array_merge( $credits[ $key ]['roles'] ?? array(), array( $role ) ) ) ) );
            }
        }
        if ( 'msc_person' === $type ) { $warnings[] = 'TVmaze supplies person facts and credits, but no biography. Choose Wikidata for Wikipedia biographies and licensed photos.'; }
        $warnings[] = 'TVmaze images are excluded: its data licence does not establish commercial image rights. Use your own licensed image.';
        return array( 'provider' => 'tvmaze', 'id' => $id, 'type' => $type, 'fields' => $fields, 'external_ids' => $ids, 'source' => $source, 'field_sources' => array(), 'credits' => $credits, 'image' => null, 'warnings' => $warnings, 'parent_tvmaze' => isset( $show['id'] ) ? (string) $show['id'] : '', 'details' => array_intersect_key( $d, array_flip( array( 'airstamp', 'type', 'birthday', 'deathday', 'gender' ) ) ) );
    }
    public static function commons( string $filename ): ?array {
        if ( '' === trim( $filename ) || strlen( $filename ) > 240 ) { return null; }
        $response = OpenClient::get( 'commons', '', array( 'action' => 'query', 'titles' => 'File:' . $filename, 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata|mime|size', 'iiurlwidth' => 1200, 'iiextmetadatalanguage' => 'en' ), DAY_IN_SECONDS );
        $pages = array_values( $response['query']['pages'] ?? array() ); $info = $pages[0]['imageinfo'][0] ?? array(); $meta = $info['extmetadata'] ?? array();
        $plain = static fn( $key ) => trim( html_entity_decode( wp_strip_all_tags( $meta[ $key ]['value'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $license_url = $plain( 'LicenseUrl' ); if ( str_starts_with( $license_url, '//' ) ) { $license_url = 'https:' . $license_url; }
        $license_url = preg_replace( '~^http://~', 'https://', $license_url );
        // An explicit allowlist: no fair use, NC, ND, undocumented or jurisdiction-only PD claims.
        if ( ! preg_match( '~^https://creativecommons\.org/(?:licenses/(?:by|by-sa)/(?:1\.0|2\.0|2\.5|3\.0|4\.0)|publicdomain/zero/1\.0)/?$~D', $license_url ) || '' !== $plain( 'Restrictions' ) || '' === $plain( 'Artist' ) || '' === $plain( 'LicenseShortName' ) ) { return null; }
        $url = $info['thumburl'] ?? $info['url'] ?? ''; $p = wp_parse_url( $url );
        if ( 'https' !== ( $p['scheme'] ?? '' ) || 'upload.wikimedia.org' !== ( $p['host'] ?? '' ) || ! empty( $p['query'] ) || ! empty( $p['port'] ) || ! in_array( $info['thumbmime'] ?? $info['mime'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) { return null; }
        $description = $info['descriptionurl'] ?? '';
        if ( ! str_starts_with( $description, 'https://commons.wikimedia.org/wiki/File:' ) ) { return null; }
        return array( 'url' => $url, 'filename' => sanitize_file_name( $filename ), 'source' => array( 'provider' => 'commons', 'url' => $description, 'license' => $plain( 'LicenseShortName' ), 'license_url' => $license_url, 'attribution' => $plain( 'Artist' ), 'credit' => $plain( 'Credit' ), 'changes' => 'Resized by Wikimedia/WordPress; displayed as a cropped catalog image.' ) );
    }
    public static function wiki( string $id, string $type ): array {
        OpenClient::qid( $id );
        if ( ! in_array( $type, array( 'msc_movie', 'msc_person' ), true ) ) { throw new SourceError( 'Use TVmaze for TV Shows and Episodes.' ); }
        $e = OpenClient::entities( array( $id ) )[ $id ] ?? array();
        if ( ! self::kind( $e, $type ) || '' === self::label( $e ) ) { throw new SourceError( 'This Wikidata item is not a supported ' . ( 'msc_person' === $type ? 'person' : 'film' ) . '.', 404 ); }
        $source = self::source( 'wikidata', 'https://www.wikidata.org/wiki/' . $id ); $fields = array( 'title' => self::label( $e ) ); $field_sources = array(); $warnings = array(); $credits = array();
        $properties = 'msc_person' === $type ? array( 'P27' => 'country', 'P106' => 'professions' ) : array( 'P495' => 'country', 'P364' => 'language', 'P136' => 'genres', 'P272' => 'production_companies', 'P750' => 'distributor' );
        $related = array(); foreach ( array_keys( $properties ) as $property ) { $related = array_merge( $related, self::ids( $e, $property ) ); }
        $roles = 'msc_movie' === $type ? array( 'P161' => 'actors', 'P57' => 'directors', 'P58' => 'screenwriters', 'P162' => 'producers' ) : array();
        foreach ( $roles as $property => $role ) { $related = array_merge( $related, array_slice( self::ids( $e, $property ), 0, 30 ) ); }
        $entities = OpenClient::entities( array_slice( array_values( array_unique( $related ) ), 0, 200 ) );
        foreach ( $properties as $property => $field ) {
            $labels = array(); foreach ( self::ids( $e, $property ) as $other ) { $label = self::label( $entities[ $other ] ?? array() ); if ( '' !== $label ) { $labels[] = $label; } }
            if ( $labels ) { $fields[ $field ] = in_array( $field, array( 'genres', 'professions' ), true ) ? $labels : ( in_array( $field, array( 'country', 'language' ), true ) ? $labels[0] : implode( ', ', $labels ) ); }
        }
        $date_property = 'msc_person' === $type ? 'P569' : 'P577'; $dates = self::claims( $e, $date_property ); $exact = array_filter( array_map( static fn( $d ) => is_array( $d ) ? self::wiki_date( $d ) : '', $dates ) ); sort( $exact );
        if ( $exact ) { $fields[ 'msc_person' === $type ? 'birth_date' : 'release_date' ] = $exact[0]; }
        if ( $dates && ! $exact ) { $warnings[] = 'The source date is incomplete or uses another calendar. It was retained in source details without inventing a day or month.'; }
        $site = self::claims( $e, 'P856' )[0] ?? ''; if ( is_string( $site ) && wp_http_validate_url( $site ) ) { $fields['official_website'] = esc_url_raw( $site ); }
        if ( 'msc_movie' === $type ) {
            $original = self::claims( $e, 'P1476' )[0] ?? array(); if ( ! empty( $original['text'] ) ) { $fields['original_title'] = sanitize_text_field( $original['text'] ); }
            $duration = self::claims( $e, 'P2047' )[0] ?? array(); $unit = $duration['unit'] ?? ''; $amount = $duration['amount'] ?? null;
            $factor = array( 'http://www.wikidata.org/entity/Q7727' => 1, 'http://www.wikidata.org/entity/Q11574' => 1 / 60, 'http://www.wikidata.org/entity/Q25235' => 60 )[ $unit ] ?? null;
            if ( null !== $factor && is_numeric( $amount ) && $amount >= 0 ) { $fields['runtime'] = round( $amount * $factor, 2 ); }
        }
        foreach ( $roles as $property => $role ) { foreach ( array_slice( self::ids( $e, $property ), 0, 30 ) as $other ) {
            $person = $entities[ $other ] ?? array(); $name = self::label( $person ); if ( '' === $name || ! in_array( 'Q5', self::ids( $person, 'P31' ), true ) ) { continue; }
            $key = 'wikidata:' . $other; $credits[ $key ] = array( 'provider' => 'wikidata', 'id' => $other, 'name' => $name, 'fields' => array( 'title' => $name ), 'source' => self::source( 'wikidata', 'https://www.wikidata.org/wiki/' . $other ), 'roles' => array_values( array_unique( array_merge( $credits[ $key ]['roles'] ?? array(), array( $role ) ) ) ) );
        } }
        $article = $e['sitelinks']['enwiki']['title'] ?? '';
        if ( '' !== $article ) { try {
            $response = OpenClient::get( 'wikipedia', '', array( 'action' => 'query', 'titles' => $article, 'prop' => 'extracts|info', 'exintro' => 1, 'explaintext' => 1, 'inprop' => 'url' ), DAY_IN_SECONDS );
            $pages = array_values( $response['query']['pages'] ?? array() ); $page = $pages[0] ?? array(); $text = trim( wp_strip_all_tags( $page['extract'] ?? '' ) );
            if ( ! empty( $page['pageid'] ) && '' !== $text && ! isset( $page['missing'] ) ) {
                $text = mb_substr( $text, 0, 20000 ); $fields['content'] = wpautop( esc_html( $text ) ); $fields['excerpt'] = wp_trim_words( $text, 55 );
                $field_sources['content'] = $field_sources['excerpt'] = self::source( 'wikipedia', 'https://en.wikipedia.org/?curid=' . (int) $page['pageid'] );
            }
        } catch ( SourceError $error ) { $warnings[] = 'Wikipedia biography/description is unavailable. Facts can still be imported; retry later for text.'; } }
        if ( empty( $fields['content'] ) ) { $warnings[] = 'No verified English Wikipedia introduction is available for this item.'; }
        $image = null; $filename = self::claims( $e, 'P18' )[0] ?? '';
        if ( is_string( $filename ) && '' !== $filename ) { try { $image = self::commons( $filename ); } catch ( SourceError $error ) { $warnings[] = 'Wikimedia Commons could not be reached. Retry later for an image.'; } }
        if ( ! $image ) { $warnings[] = 'No image with a supported, explicit Commons licence is available. Existing images are retained.'; }
        $ids = array( 'wikidata' => $id ); $imdb = self::claims( $e, 'P345' )[0] ?? '';
        if ( is_string( $imdb ) && preg_match( 'msc_person' === $type ? '/^nm[0-9]{7,12}$/D' : '/^tt[0-9]{7,12}$/D', $imdb ) ) { $ids['imdb'] = $imdb; }
        return array( 'provider' => 'wikidata', 'id' => $id, 'type' => $type, 'fields' => $fields, 'external_ids' => $ids, 'source' => $source, 'field_sources' => $field_sources, 'credits' => $credits, 'image' => $image, 'warnings' => $warnings, 'parent_tvmaze' => '', 'details' => array( 'dates' => $dates, 'description' => sanitize_text_field( $e['descriptions']['en']['value'] ?? '' ) ) );
    }
    public static function details( string $provider, string $id, string $type ): array {
        if ( 'tvmaze' === $provider ) { return self::tv( $id, $type ); }
        if ( 'wikidata' === $provider ) { return self::wiki( $id, $type ); }
        throw new SourceError( 'Choose a supported source.' );
    }
}
