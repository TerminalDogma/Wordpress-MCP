<?php
namespace MSC;

/** Find editorial coverage through the current site's native WordPress tags. */
final class CatalogArticles {
    public static function query( array $record, int $page ): \WP_Query {
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
        return new \WP_Query( array(
            'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
            'posts_per_page' => 4, 'paged' => $page,
            'ignore_sticky_posts' => true, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
            'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $tags ) ),
        ) );
    }
}
