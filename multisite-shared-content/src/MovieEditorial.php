<?php
namespace MSC;

/** Editorial movie fields and data shared by the movie presentation and metadata. */
final class MovieEditorial {
    public const CREW = array( 'cinematographers' => 'Cinematography', 'film_editors' => 'Edited by', 'composers' => 'Music by' );

    public static function fields(): array {
        $fields = array();
        foreach ( array( 'film_logline' => 'Original one-line logline', 'film_tagline' => 'Official tagline', 'rating_reason' => 'Content rating reason', 'franchise_context' => 'Where the film fits in its franchise', 'box_office_currency' => 'Box office currency (e.g. USD)' ) as $key => $label ) {
            $fields[ $key ] = array( 'type' => 'text', 'label' => $label );
        }
        foreach ( array( 'editorial_synopsis' => 'Original spoiler-free synopsis', 'spoiler_synopsis' => 'Synopsis with spoilers' ) as $key => $label ) {
            $fields[ $key ] = array( 'type' => 'richtext', 'label' => $label );
        }
        foreach ( self::CREW as $key => $label ) {
            $fields[ $key ] = array( 'type' => 'relationship_list', 'target' => 'msc_person', 'label' => $label );
        }
        foreach ( array( 'post_credits_count' => 'Post-credits scenes (0 for none)', 'film_budget' => 'Production budget', 'domestic_opening' => 'Domestic opening weekend', 'worldwide_gross' => 'Worldwide box office', 'rotten_tomatoes_review_count' => 'Tomatometer review count', 'rotten_tomatoes_audience_count' => 'Popcornmeter rating count or lower bound', 'metacritic_review_count' => 'Metacritic critic review count', 'metacritic_positive_count' => 'Metacritic positive reviews', 'metacritic_mixed_count' => 'Metacritic mixed reviews' ) as $key => $label ) {
            $fields[ $key ] = array( 'type' => 'integer', 'min' => 0, 'label' => $label );
        }
        $fields['rotten_tomatoes_audience_count_plus'] = array( 'type' => 'boolean', 'label' => 'Popcornmeter count is a lower bound' );
        $fields['rotten_tomatoes_audience_score'] = array( 'type' => 'number', 'min' => 0, 'max' => 100, 'label' => 'Popcornmeter score (%)' );
        $fields['box_office_url'] = array( 'type' => 'url', 'format' => 'movie_editorial_link', 'label' => 'Box office source URL' );
        $fields['trailer_source_url'] = array( 'type' => 'url', 'format' => 'movie_editorial_link', 'label' => 'Official trailer page (fallback when no video is embedded)' );
        $fields['movie_faq'] = array( 'type' => 'json', 'label' => 'Editorial questions and answers', 'instructions' => 'List of objects with question and answer.' );
        $fields['watch_options'] = array( 'type' => 'json', 'label' => 'Where to watch', 'instructions' => 'List of objects with service, url, region, kind and optional checked (YYYY-MM-DD). Link to verified offers or a provider’s availability page.' );
        $fields['release_history'] = array( 'type' => 'json', 'label' => 'Release history', 'instructions' => 'List of objects with date (YYYY-MM-DD), label and optional region and source URL (url).' );
        return $fields;
    }

    public static function rows( array $data, string $field ): array {
        $value = $data[ $field ] ?? array();
        return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
    }

    public static function watch( array $data ): array {
        $rows = array();
        foreach ( self::rows( $data, 'watch_options' ) as $row ) {
            $url = self::link( $row['url'] ?? '' );
            if ( ! $url || empty( $row['service'] ) || ! is_string( $row['service'] ) ) { continue; }
            $rows[] = array(
                'service' => $row['service'], 'url' => $url,
                'region' => is_string( $row['region'] ?? null ) ? $row['region'] : '',
                'kind' => is_string( $row['kind'] ?? null ) ? $row['kind'] : '',
                'checked' => ReleaseCalendar::date( $row['checked'] ?? '' ),
            );
        }
        return $rows;
    }

    public static function releases( array $data ): array {
        $releases = array();
        foreach ( array_merge( self::rows( $data, 'release_history' ), self::rows( $data, 'additional_release_dates' ) ) as $row ) {
            $date = ReleaseCalendar::date( $row['date'] ?? '' );
            if ( ! $date ) { continue; }
            $label = is_string( $row['label'] ?? null ) && trim( $row['label'] ) ? $row['label'] : 'Release';
            $releases[] = array( 'date' => $date, 'label' => $label, 'region' => is_string( $row['region'] ?? null ) ? $row['region'] : '', 'url' => self::link( $row['url'] ?? '' ) );
        }
        foreach ( array( 'release_date' => 'Theatrical release', 'digital_release_date' => 'Digital release', 'streaming_release_date' => 'Streaming release', 'physical_release_date' => 'Physical release' ) as $key => $label ) {
            $date = ReleaseCalendar::date( $data[ $key ] ?? '' );
            if ( $date && ! in_array( $date, array_column( $releases, 'date' ), true ) ) {
                $releases[] = array( 'date' => $date, 'label' => $label, 'region' => '', 'url' => '' );
            }
        }
        usort( $releases, static fn( $a, $b ) => $a['date'] <=> $b['date'] );
        return $releases;
    }

    public static function link( $url ): string {
        $parts = is_string( $url ) ? wp_parse_url( $url ) : false;
        return $parts && in_array( strtolower( $parts['scheme'] ?? '' ), array( 'https', 'http' ), true ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? esc_url_raw( $url, array( 'https', 'http' ) ) : '';
    }

    /** Show the sequence this site exposes, with the published timeline’s phase and story date. */
    public static function franchise_orders( array $record, array $franchise, array $timelines ): array {
        $uuid = $record['uuid'];
        $orders = array();
        $release = Franchises::members( $franchise );
        foreach ( $release as $position => $item ) {
            if ( $item['uuid'] === $uuid ) {
                $orders['Release order'] = array( 'position' => $position + 1, 'prev' => $release[ $position - 1 ] ?? null, 'next' => $release[ $position + 1 ] ?? null );
                break;
            }
        }
        foreach ( $timelines as $timeline ) {
            if ( ( $timeline['data']['franchise'] ?? '' ) !== $franchise['uuid'] ) { continue; }
            $model = Timelines::model( $timeline['data']['chronology'] ?? array() );
            $ids = array();
            $match = null;
            foreach ( $model['entries'] ?? array() as $index => $entry ) {
                $id = $entry['item'] ?? '';
                if ( ! $id || isset( $ids[ $id ] ) ) { continue; }
                $ids[ $id ] = $index;
                if ( $id === $uuid ) { $match = $entry; }
            }
            if ( ! $match ) { continue; }
            $members = array_column( $release, null, 'uuid' );
            $story = array_keys( array_intersect_key( $ids, $members ) );
            $position = array_search( $uuid, $story, true );
            if ( false === $position ) { continue; }
            $eras = array_column( $model['eras'] ?? array(), 'label', 'id' );
            $orders['Story order'] = array(
                'position' => $position + 1, 'prev' => $members[ $story[ $position - 1 ] ?? '' ] ?? null,
                'next' => $members[ $story[ $position + 1 ] ?? '' ] ?? null,
                'phase' => $eras[ $match['era'] ?? '' ] ?? '', 'date' => $match['date'] ?? '',
                'timeline' => Presentation::url( $timeline ) . '#timeline-entry-' . ( $ids[ $uuid ] + 1 ),
            );
            break;
        }
        return $orders;
    }

    public static function structured_data( array $record, array $credits, ?array $review ): string {
        $d = $record['data'];
        $url = Presentation::url( $record );
        $movie = array( '@context' => 'https://schema.org', '@type' => 'Movie', '@id' => $url . '#movie', 'url' => $url, 'name' => $d['title'] );
        $description = wp_strip_all_tags( $d['film_logline'] ?? ( $d['editorial_synopsis'] ?? ( $d['excerpt'] ?? '' ) ) );
        if ( $description ) { $movie['description'] = $description; }
        if ( $date = ReleaseCalendar::date( $d['release_date'] ?? '' ) ) { $movie['datePublished'] = $date; }
        if ( ! empty( $d['runtime'] ) ) { $movie['duration'] = 'PT' . (int) $d['runtime'] . 'M'; }
        foreach ( array( 'content_rating' => 'contentRating', 'genres' => 'genre', 'language' => 'inLanguage' ) as $key => $property ) {
            if ( ! empty( $d[ $key ] ) ) { $movie[ $property ] = $d[ $key ]; }
        }
        $art = Presentation::artwork( $record );
        if ( ! empty( $art['url'] ) ) { $movie['image'] = $art['url']; }
        foreach ( array( 'actors' => 'actor', 'directors' => 'director', 'composers' => 'musicBy' ) as $key => $property ) {
            foreach ( $credits[ $key ]['items'] ?? array() as $credit ) {
                $person = array( '@type' => 'Person', 'name' => $credit['title'] );
                if ( $credit['url'] ) { $person['url'] = $credit['url']; }
                $movie[ $property ][] = $person;
            }
        }
        if ( ! empty( $review['review_id'] ) && ( $post = get_post( $review['review_id'] ) ) && ! empty( $review['url'] ) ) {
            $review_data = array(
                '@type' => 'Review', 'url' => $review['url'], 'name' => get_the_title( $post ),
                'datePublished' => get_the_date( DATE_W3C, $post ),
                'reviewRating' => array( '@type' => 'Rating', 'ratingValue' => $review['score'], 'bestRating' => 10, 'worstRating' => 1 ),
                'publisher' => array( '@type' => 'Organization', 'name' => $review['name'] ),
            );
            if ( $author = get_the_author_meta( 'display_name', (int) $post->post_author ) ) { $review_data['author'] = array( '@type' => 'Person', 'name' => $author ); }
            $movie['review'] = $review_data;
        }
        return '<script type="application/ld+json">' . wp_json_encode( $movie, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
    }
}
