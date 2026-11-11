<?php
namespace MSC;

/** Live enrichment by exact source identity; never generate substitute facts or art. */
final class TrainingSources {
    private static function names( string $text ): array {
        if ( in_array( trim( $text ), array( '', 'N/A' ), true ) ) { return array(); }
        return array_values( array_filter( array_map( static fn( $s ) => trim( preg_replace( '/\s*\([^)]*\)/u', '', $s ) ), explode( ',', $text ) ) ) );
    }
    public static function fetch( array $row ): array {
        return OpenClient::paced( static function () use ( $row ) {
            $type = $row['type']; $ids = $row['fields']['external_ids']; $fields = $row['fields']; unset( $fields['content'], $fields['excerpt'] );
            $omdb_details = array(); $credits = array(); $priority = array(); $sources = array(); $poster = $featured = ''; $image_credit = ''; $warnings = array();
            $entity = OpenClient::entities( array( $row['id'] ) )[ $row['id'] ] ?? array();
            if ( ( $entity['id'] ?? '' ) !== $row['id'] ) { throw new \RuntimeException( 'Wikidata did not return the requested title identity.' ); }
            // Use the article's actual lead and primary artwork, rather than a Wikidata description.
            $article = $entity['sitelinks']['enwiki']['title'] ?? '';
            if ( $article ) {
                $wiki = OpenClient::get( 'wikipedia', '', array( 'action' => 'query', 'titles' => $article, 'prop' => 'extracts|pageimages', 'exintro' => 1, 'explaintext' => 1, 'piprop' => 'original', 'pilicense' => 'any', 'redirects' => 1 ) );
                $page = array_values( $wiki['query']['pages'] ?? array() )[0] ?? array(); $text = trim( $page['extract'] ?? '' );
                if ( $text ) { $fields['content'] = OpenSources::wikipedia_paragraphs( $text ); $fields['excerpt'] = wp_trim_words( $text, 45 ); }
                $poster = $page['original']['source'] ?? ''; $image_credit = 'Wikipedia article artwork';
                if ( ! empty( $page['pageid'] ) ) { $sources['content'] = $sources['excerpt'] = OpenSources::source( 'wikipedia', 'https://en.wikipedia.org/?curid=' . (int) $page['pageid'] ); }
            }
            if ( ! empty( $ids['imdb'] ) && OmdbClient::key() ) {
                try {
                    $omdb = OmdbClient::details( $ids['imdb'] );
                    if ( ( OmdbImport::TYPES[ $omdb['Type'] ] ?? '' ) !== $type ) { throw new \RuntimeException( 'OMDb returned a different title type.' ); }
                    $omdb_details = $omdb; $priority = self::names( $omdb['Actors'] ?? '' );
                    $mapped = OmdbImport::map( $omdb, $type ); unset( $mapped['additional_metadata'] );
                    $fields = array_replace( $fields, $mapped );
                    foreach ( array_keys( $mapped ) as $field ) { $sources[ $field ] = array( 'provider' => 'omdb', 'url' => 'https://www.omdbapi.com/', 'attribution' => 'OMDb', 'license' => '', 'license_url' => '', 'changes' => 'Formatted source metadata.' ); }
                    if ( ! empty( $omdb['Poster'] ) ) { $poster = $omdb['Poster']; $image_credit = 'Poster supplied by OMDb'; }
                    foreach ( array( 'Actors' => 'actors', 'Director' => 'directors', 'Writer' => 'screenwriters' ) as $key => $role ) { foreach ( self::names( $omdb[ $key ] ?? '' ) as $name ) { $credits[] = array( 'name' => $name, 'roles' => array( $role ), 'fields' => array( 'title' => $name ), 'provider' => 'omdb_credit', 'id' => hash( 'sha256', PersonIdentity::name( $name ) ), 'source' => $sources['title'] ); } }
                } catch ( OmdbError $e ) { $warnings[] = $e->getMessage(); }
            }
            $roles = array( 'P161' => 'actors', 'P57' => 'directors', 'P58' => 'screenwriters', 'P162' => 'producers', 'P170' => 'creators' ); $people_ids = array();
            foreach ( $roles as $property => $role ) { foreach ( array_slice( OpenSources::claims( $entity, $property ), 0, 60 ) as $value ) { if ( ! empty( $value['id'] ) ) { $people_ids[] = $value['id']; } } }
            $company_ids = array(); foreach ( array( 'P272', 'P750' ) as $property ) { foreach ( OpenSources::claims( $entity, $property ) as $value ) { if ( ! empty( $value['id'] ) ) { $company_ids[] = $value['id']; } } }
            $people = OpenClient::entities( array_values( array_unique( array_merge( $people_ids, $company_ids ) ) ) ); $companies = array();
            foreach ( array( 'P272' => array( 'production_companies', 'production_company_records', 'msc_studio' ), 'P750' => array( 'distributor', 'distributor_records', 'msc_distributor' ) ) as $property => $definition ) {
                $names = array(); foreach ( OpenSources::claims( $entity, $property ) as $value ) { $id = $value['id'] ?? ''; $name = $people[ $id ]['labels']['en']['value'] ?? ''; if ( ! $name ) { continue; } $names[] = $name; $companies[] = array( 'id' => $id, 'name' => $name, 'field' => $definition[1], 'type' => $definition[2] ); } if ( $names ) { $fields[ $definition[0] ] = implode( ', ', $names ); }
            }
            foreach ( $roles as $property => $role ) { foreach ( array_slice( OpenSources::claims( $entity, $property ), 0, 60 ) as $value ) {
                $id = $value['id'] ?? ''; $p = $people[ $id ] ?? array(); $name = $p['labels']['en']['value'] ?? '';
                if ( ! $name || ! in_array( 'Q5', array_column( OpenSources::claims( $p, 'P31' ), 'id' ), true ) ) { continue; } $external = array( 'wikidata' => $id ); $imdb = OpenSources::claims( $p, 'P345' )[0] ?? '';
                if ( is_string( $imdb ) && preg_match( '/^nm[0-9]{7,12}$/D', $imdb ) ) { $external['imdb'] = $imdb; }
                $credits[] = array( 'name' => $name, 'roles' => array( $role ), 'fields' => array( 'title' => $name ), 'external_ids' => $external, 'provider' => 'wikidata', 'id' => $id, 'source' => OpenSources::source( 'wikidata', 'https://www.wikidata.org/wiki/' . $id ) );
            } }
            if ( 'msc_show' === $type && ! empty( $ids['imdb'] ) ) {
                try {
                    $tv = OpenClient::get( 'tvmaze', 'lookup/shows', array( 'imdb' => $ids['imdb'] ) );
                    if ( ( $tv['externals']['imdb'] ?? '' ) !== $ids['imdb'] ) { throw new \RuntimeException( 'TVmaze returned a different IMDb identity.' ); }
                    $tv_id = (string) (int) $tv['id']; $snapshot = OpenSources::tv( $tv_id, 'msc_show' ); $ids['tvmaze'] = $tv_id;
                    $fields = array_replace( $fields, $snapshot['fields'] );
                    foreach ( array_keys( $snapshot['fields'] ) as $field ) { $sources[ $field ] = $snapshot['source']; }
                    $credits = array_merge( $credits, array_values( $snapshot['credits'] ) );
                    foreach ( $snapshot['credits'] as $credit ) { if ( in_array( 'actors', $credit['roles'], true ) ) { $priority[] = $credit['name']; } }
                    foreach ( array( 'actors', 'directors', 'screenwriters', 'producers', 'creators' ) as $role ) { $sources[ $role ] = $snapshot['source']; }
                    if ( ! empty( $tv['image']['original'] ) ) { $poster = $tv['image']['original']; $image_credit = 'TVmaze artwork'; }
                    $images = OpenClient::get( 'tvmaze', 'shows/' . $tv_id . '/images' );
                    foreach ( $images as $image ) { if ( 'background' === ( $image['type'] ?? '' ) && ! empty( $image['resolutions']['original']['url'] ) ) { $featured = $image['resolutions']['original']['url']; break; } }
                } catch ( SourceError $e ) { if ( 429 === $e->status ) { throw $e; } $warnings[] = $e->getMessage(); }
            }
            // Prefer identified credits over name-only OMDb credits; merge roles before creating People.
            usort( $credits, static fn( $a, $b ) => ( 'omdb_credit' === $a['provider'] ) <=> ( 'omdb_credit' === $b['provider'] ) ); $merged = array();
            foreach ( $credits as $credit ) {
                $key = PersonIdentity::name( $credit['name'] );
                if ( isset( $merged[ $key ] ) ) {
                    foreach ( array( 'wikidata', 'imdb', 'tvmaze' ) as $provider ) { $a = PersonIdentity::credit_ids( $merged[ $key ] )[ $provider ] ?? ''; $b = PersonIdentity::credit_ids( $credit )[ $provider ] ?? ''; if ( $a && $b && $a !== $b ) { throw new \RuntimeException( 'Conflicting same-name credits require review: ' . $credit['name'] ); } }
                    $merged[ $key ]['external_ids'] = PersonIdentity::credit_ids( $merged[ $key ] ) + PersonIdentity::credit_ids( $credit );
                    $merged[ $key ]['roles'] = array_values( array_unique( array_merge( $merged[ $key ]['roles'], $credit['roles'] ) ) );
                } else { $merged[ $key ] = $credit; }
            }
            $ordered = array(); foreach ( $priority as $name ) { $key = PersonIdentity::name( $name ); if ( isset( $merged[ $key ] ) ) { $ordered[ $key ] = $merged[ $key ]; } } $merged = $ordered + $merged;
            foreach ( array( 'poster' => $poster, 'featured_image' => $featured ?: $poster ) as $field => $url ) { $sources[ $field ] = array( 'provider' => 'artwork', 'url' => $url, 'attribution' => $image_credit, 'license' => '', 'license_url' => '', 'changes' => 'Downloaded source artwork.' ); }
            $result = array( 'fields' => $fields, 'credits' => array_values( $merged ), 'companies' => $companies, 'omdb' => $omdb_details, 'external_ids' => $ids, 'poster_url' => $poster, 'featured_url' => $featured ?: $poster, 'image_credit' => $image_credit, 'field_sources' => $sources, 'warnings' => $warnings );
            self::validate( $result ); return $result;
        }, 20 );
    }
    public static function validate( array $source ): void {
        $fields = $source['fields'] ?? array(); $roles = array_merge( array(), ...array_column( $source['credits'] ?? array(), 'roles' ) );
        if ( mb_strlen( trim( wp_strip_all_tags( $fields['content'] ?? '' ) ) ) < 100 || empty( $fields['genres'] ) || ! array_intersect( $roles, array( 'actors' ) ) || ! array_intersect( $roles, array( 'directors', 'screenwriters', 'producers', 'creators' ) ) || empty( $source['poster_url'] ) || empty( $source['featured_url'] ) ) { throw new \RuntimeException( 'Incomplete source: a full description, genres, cast, crew, poster and featured artwork are required. No partial entry was created.' ); }
        foreach ( array( 'poster_url', 'featured_url' ) as $key ) { $url = $source[ $key ]; $host = strtolower( wp_parse_url( $url, PHP_URL_HOST ) ?? '' ); if ( ! OmdbClient::poster_url( $url ) && ! ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && in_array( $host, array( 'upload.wikimedia.org', 'static.tvmaze.com' ), true ) ) ) { throw new \RuntimeException( 'Unsupported training image source.' ); } }
    }
}
