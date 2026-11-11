<?php
namespace MSC;

/** Shared franchise hubs; membership uses portable global record UUIDs. */
final class Franchises {
    public static function schemas( array $common ): array {
        return array( 'msc_franchise' => array( 'label' => 'Franchises', 'slug' => 'franchises', 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $common + array(
            'theme_color' => array( 'type' => 'text', 'label' => 'Franchise accent color', 'instructions' => 'Optional hex color, such as #b90d24. Leave empty to use the site theme.' ),
            'poster' => array( 'type' => 'media', 'label' => 'Franchise poster' ),
            'movies' => array( 'type' => 'relationship_list', 'target' => 'msc_movie', 'label' => 'Movies', 'instructions' => 'Add movies to this franchise. Draft titles remain available to editors and appear publicly once published on this site.' ),
            'shows' => array( 'type' => 'relationship_list', 'target' => 'msc_show', 'label' => 'TV Shows' ),
            'genres' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_genre' ),
        ) ) );
    }
    public static function enroll(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0 ) ) as $site ) {
            $id = (int) $site->blog_id;
            if ( get_blog_option( $id, 'msc_franchises_profile_version' ) ) { continue; }
            if ( Schema::enabled( 'msc_movie', $id ) || Schema::enabled( 'msc_show', $id ) ) {
                Schema::participate( $id, array_values( array_unique( array_merge( get_blog_option( $id, 'msc_types', array() ), array( 'msc_franchise' ) ) ) ) );
                update_blog_option( $id, 'msc_franchises_profile_version', 1 );
            }
        }
    }
    /** Release chronology, with undated entries last and stable title ordering. */
    public static function members( array $franchise ): array {
        $out = array();
        if ( 'msc_franchise' !== $franchise['type'] ) { return $out; }
        foreach ( array( 'movies' => 'msc_movie', 'shows' => 'msc_show' ) as $field => $type ) {
            foreach ( (array) ( $franchise['data'][ $field ] ?? array() ) as $uuid ) {
                $record = Presentation::record( $uuid );
                if ( ! $record || $record['type'] !== $type ) { continue; }
                $record['franchise_date'] = $record['data'][ 'msc_show' === $type ? 'premiere_date' : 'release_date' ] ?? '';
                $out[ $uuid ] = $record;
            }
        }
        uasort( $out, static fn( $a, $b ) => array( $a['franchise_date'] ?: '9999', $a['data']['title'], $a['uuid'] ) <=> array( $b['franchise_date'] ?: '9999', $b['data']['title'], $b['uuid'] ) );
        return array_values( $out );
    }
    public static function related( array $item ): array {
        if ( ! Schema::enabled( 'msc_franchise' ) || ! in_array( $item['type'], array( 'msc_movie', 'msc_show' ), true ) ) { return array(); }
        $field = 'msc_movie' === $item['type'] ? 'movies' : 'shows'; $out = array();
        $posts = get_posts( array( 'post_type' => 'msc_franchise', 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_query' => array( array( 'key' => 'msc_' . $field, 'value' => $item['uuid'], 'compare' => 'LIKE' ) ) ) );
        foreach ( $posts as $post ) { $global = Plugin::store()->by_post( $post->ID ); $r = $global ? Presentation::record( $global['id'] ) : null; if ( $r && in_array( $item['uuid'], (array) ( $r['data'][ $field ] ?? array() ), true ) ) { $out[] = $r; } }
        return $out;
    }
    public static function links( array $item ): void {
        $records = self::related( $item ); if ( ! $records ) { return; }
        echo '<nav class="franchise-links" aria-label="Franchises"><strong>Franchise</strong> ';
        foreach ( $records as $r ) { echo '<a class="chip" href="' . esc_url( Presentation::url( $r ) ) . '">' . esc_html( $r['data']['title'] ) . '</a>'; } echo '</nav>';
    }
}
