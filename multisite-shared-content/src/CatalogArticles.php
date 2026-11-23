<?php
namespace MSC;

/** Find editorial coverage through the current site's native WordPress tags. */
final class CatalogArticles {
    public const COVERAGE = array( 'reviews' => 'Reviews', 'interviews' => 'Interviews', 'features' => 'Features', 'news' => 'News' );

    public static function query( array $record, int $page, string $kind = '', string $order = 'DESC', int $page_size = 4 ): \WP_Query {
        $data = $record['data'];
        $post_id = (int) ( $record['mapping']['post_id'] ?? 0 );
        $slug = $post_id ? get_post_field( 'post_name', $post_id ) : ( $data['slug'] ?? '' );
        $tags = array( $slug );
        $parent_key = array( 'msc_episode' => 'parent_show', 'msc_comic_issue' => 'parent_comic' )[ $record['type'] ] ?? null;
        if ( $parent_key ) {
            // A short episode or issue title can be reused in different series.
            $parent = empty( $data[ $parent_key ] ) ? null : Presentation::record( $data[ $parent_key ] );
            if ( $parent ) { $tags[] = $parent['data']['title'] . ' ' . $data['title']; }
        } else {
            $tags[] = $data['title'];
        }
        $tags = array_values( array_unique( array_filter( array_map( 'sanitize_title', $tags ) ) ) );
        if ( 'msc_franchise' === $record['type'] && array_intersect( $tags, array( 'marvel-cinematic-universe', 'mcu' ) ) ) {
            $tags = array( 'marvel-cinematic-universe', 'mcu', ...$tags );
        }
        $tax = array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags ) );
        if ( isset( self::COVERAGE[ $kind ] ) ) {
            $news = 'news' === $kind;
            $slugs = $news ? array( 'reviews', 'review', 'interviews', 'interview', 'features', 'feature' ) : array( $kind, rtrim( $kind, 's' ) );
            $tax[] = array( 'relation' => $news ? 'AND' : 'OR',
                array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $slugs, 'operator' => $news ? 'NOT IN' : 'IN' ),
                array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $slugs, 'operator' => $news ? 'NOT IN' : 'IN' ),
            );
        }
        $order = 'ASC' === $order ? 'ASC' : 'DESC';
        return new \WP_Query( array(
            'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
            'posts_per_page' => $page_size, 'paged' => $page,
            'ignore_sticky_posts' => true, 'orderby' => array( 'date' => $order, 'ID' => $order ),
            'tax_query' => $tax,
        ) );
    }

    /** Merge published house reviews with tagged coverage in the selected date order. */
    public static function movie_coverage( array $record, int $page, string $kind, bool $oldest ): array {
        $order = $oldest ? 'ASC' : 'DESC';
        $reviews = Reviews::enabled() && in_array( $kind, array( '', 'reviews' ), true ) ? get_posts( array(
            'post_type' => 'msc_review', 'post_status' => 'publish', 'has_password' => false,
            'numberposts' => -1, 'meta_key' => '_msc_review_item', 'meta_value' => $record['uuid'],
        ) ) : array();
        $articles = self::query( $record, $reviews ? 1 : $page, $kind, $order );
        if ( ! $reviews ) { return array( 'posts' => $articles->posts, 'pages' => (int) $articles->max_num_pages ); }

        $page_size = 4;
        $total = (int) $articles->found_posts;
        $pages = (int) ceil( ( $total + count( $reviews ) ) / $page_size );
        if ( $page > $pages ) { return array( 'posts' => array(), 'pages' => $pages ); }
        $needed = min( $total, $page * $page_size );
        $posts = $needed > $page_size ? self::query( $record, 1, $kind, $order, $needed )->posts : $articles->posts;
        $posts = array_merge( $posts, $reviews );
        usort( $posts, static function ( \WP_Post $a, \WP_Post $b ) use ( $oldest ): int {
            $difference = array( $a->post_date, $a->ID ) <=> array( $b->post_date, $b->ID );
            return $oldest ? $difference : -$difference;
        } );
        return array( 'posts' => array_slice( $posts, ( $page - 1 ) * $page_size, $page_size ), 'pages' => $pages );
    }

    public static function coverage_kind( \WP_Post $post ): string {
        if ( 'msc_review' === $post->post_type ) { return 'Reviews'; }
        $terms = wp_get_post_terms( $post->ID, array( 'category', 'post_tag' ), array( 'fields' => 'slugs' ) );
        foreach ( self::COVERAGE as $slug => $label ) {
            if ( is_array( $terms ) && array_intersect( $terms, array( $slug, rtrim( $slug, 's' ) ) ) ) { return $label; }
        }
        return 'News';
    }

    public static function related_movies( \WP_Post $post ): array {
        if ( 'post' !== $post->post_type || ! Schema::enabled( 'msc_movie' ) || post_password_required( $post ) ) { return array(); }
        $tags = get_the_tags( $post->ID );
        if ( ! $tags || is_wp_error( $tags ) ) { return array(); }
        $base = array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 'has_password' => false, 'fields' => 'ids', 'posts_per_page' => 24 );
        $by_slug = get_posts( $base + array( 'post_name__in' => wp_list_pluck( $tags, 'slug' ) ) );
        $ids = array_fill_keys( $by_slug, true );
        foreach ( $tags as $tag ) {
            foreach ( get_posts( $base + array( 'title' => $tag->name ) ) as $id ) { $ids[ $id ] = true; }
        }
        $records = array();
        foreach ( array_keys( $ids ) as $id ) {
            $shared = Plugin::store()->by_post( $id );
            if ( $shared && ( $record = Presentation::record( $shared['id'] ) ) ) { $records[] = $record; }
        }
        return $records;
    }
}
