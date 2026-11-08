<?php
namespace MSC;

/** Named catalog entities, portable relationships and publication-aware detail links. */
final class EntityCatalog {
    public static function definitions(): array {
        return array(
            'msc_studio' => array( 'Production Companies', 'Production Company', 'production-companies', 'production_company_records', 'production_companies', array( 'msc_movie', 'msc_show', 'msc_episode' ) ),
            'msc_distributor' => array( 'Distributors', 'Distributor', 'distributors', 'distributor_records', 'distributor', array( 'msc_movie', 'msc_show' ) ),
            'msc_developer' => array( 'Game Developers', 'Game Developer', 'game-developers', 'developer_records', 'developers', array( 'msc_game' ) ),
            'msc_publisher' => array( 'Game Publishers', 'Game Publisher', 'game-publishers', 'publisher_records', 'publishers', array( 'msc_game' ) ),
            'msc_console' => array( 'Game Consoles and Platforms', 'Game Console / Platform', 'game-platforms', 'console_records', 'platforms', array( 'msc_game' ) ),
            'msc_streaming' => array( 'Streaming Services', 'Streaming Service', 'streaming-services', 'streaming_service_records', 'streaming_service', array( 'msc_movie', 'msc_show', 'msc_episode' ) ),
            'msc_tv_network' => array( 'TV Networks', 'TV Network', 'tv-networks', 'tv_network_records', 'network', array( 'msc_show', 'msc_episode' ) ),
        );
    }
    public static function types_for( array $types ): array {
        $out = array(); foreach ( self::definitions() as $type => $d ) { if ( array_intersect( $types, $d[5] ) ) { $out[] = $type; } } return $out;
    }
    public static function fields( string $type ): array {
        $fields = array(); foreach ( self::definitions() as $target => $d ) { if ( in_array( $type, $d[5], true ) ) { $fields[ $d[3] ] = array( 'type' => 'relationship_list', 'target' => $target, 'label' => 'Linked ' . $d[0] ); } } return $fields;
    }
    public static function schemas( array $common ): array {
        $out = array(); foreach ( self::definitions() as $type => $d ) {
            $fields = $common + array( 'logo' => array( 'type' => 'media', 'label' => 'Logo / product image' ), 'official_website' => array( 'type' => 'url' ), 'country' => array( 'type' => 'text', 'taxonomy' => 'msc_country' ), 'founded_date' => array( 'type' => 'date' ), 'headquarters' => array( 'type' => 'text' ), 'external_ids' => array( 'type' => 'json' ) );
            if ( 'msc_console' === $type ) { unset( $fields['founded_date'] ); $fields += array( 'release_date' => array( 'type' => 'date', 'indexed' => true, 'label' => 'Launch date' ), 'manufacturer' => array( 'type' => 'text' ), 'platform_id' => array( 'type' => 'integer', 'min' => 1, 'label' => 'IGDB platform ID (optional)' ), 'platform_kind' => array( 'type' => 'select', 'options' => array( 'Home console', 'Handheld', 'VR', 'Computer', 'Mobile', 'Other' ) ) ); }
            $out[ $type ] = array( 'label' => $d[0], 'slug' => $d[2], 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $fields );
        } return $out;
    }
    /** Add appropriate new types once without re-enabling later editorial exclusions. */
    public static function enroll(): void {
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0 ) ) as $site ) {
            $id = (int) $site->blog_id; if ( get_blog_option( $id, 'msc_entity_catalog_version', 0 ) >= 1 ) { continue; }
            $types = get_blog_option( $id, 'msc_types', array() ); $new = array_values( array_unique( array_merge( $types, self::types_for( $types ) ) ) );
            if ( $new !== $types ) { Schema::participate( $id, $new ); } update_blog_option( $id, 'msc_entity_catalog_version', 1 );
        }
    }
    public static function legacy_fields( string $type ): array {
        $keys = array(); $schema = Schema::get( $type ); foreach ( self::definitions() as $d ) { if ( isset( $schema['fields'][ $d[3] ] ) && in_array( $type, $d[5], true ) ) { $keys[] = $d[4]; } } return $keys;
    }
    private static function name( string $value ): string { return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) ) ); }
    /** Ambiguous names stay text; an explicit UUID can resolve a namesake. */
    private static function named( string $type, string $name ): ?array {
        if ( ! Schema::enabled( $type ) ) { return null; } global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status='publish' AND post_password='' AND post_title=%s LIMIT 3", $type, $name ) );
        $matches = array(); foreach ( $ids as $id ) { $g = Plugin::store()->by_post( (int) $id ); $r = $g ? Presentation::record( $g['id'] ) : null; if ( $r && self::name( $r['data']['title'] ?? '' ) === self::name( $name ) ) { $matches[] = $r; } } return 1 === count( $matches ) ? $matches[0] : null;
    }
    /** Explicit company/Person names remain visible; only public destinations receive links. */
    public static function rows( array $record ): array {
        $rows = array(); $schema = Schema::get( $record['type'] ); $definitions = self::definitions();
        foreach ( $schema['fields'] as $key => $field ) {
            if ( ! in_array( $field['type'], array( 'relationship', 'relationship_list' ), true ) ) { continue; }
            $items = array(); $seen = array();
            foreach ( (array) ( $record['data'][ $key ] ?? array() ) as $uuid ) {
                if ( ! is_string( $uuid ) || ! $uuid ) { continue; } $credit = Presentation::credit( $uuid, $field['target'] ?? null );
                if ( ! $credit ) { continue; }
                $items[] = array_intersect_key( $credit, array_flip( array( 'title', 'url', 'uuid' ) ) ); $seen[ self::name( $credit['title'] ) ] = true;
            }
            $d = $definitions[ $field['target'] ?? '' ] ?? null; $legacy = $d && $d[3] === $key ? ( $record['data'][ $d[4] ] ?? array() ) : array();
            foreach ( (array) $legacy as $value ) {
                if ( ! is_string( $value ) || '' === trim( $value ) ) { continue; }
                $match = self::named( $field['target'], $value );
                $names = $match || is_array( $legacy ) || ! in_array( $d[4], array( 'production_companies', 'distributor' ), true ) ? array( $value ) : preg_split( '/\s*[,;]\s*/u', $value );
                foreach ( $names as $name ) {
                    if ( '' === trim( $name ) || isset( $seen[ self::name( $name ) ] ) ) { continue; } $seen[ self::name( $name ) ] = true;
                    $r = ! empty( $record['overrides'][ $key ] ) || ! array_key_exists( $key, $record['overrides'] ?? array() ) ? ( $match ?? self::named( $field['target'], $name ) ) : null;
                    $items[] = array( 'title' => $name, 'url' => $r ? Presentation::url( $r ) : '', 'uuid' => $r['uuid'] ?? '' );
                }
            }
            if ( $items ) { $rows[ $key ] = array( 'label' => $d && $d[3] === $key ? $d[0] : ( $field['label'] ?? ucwords( str_replace( '_', ' ', $key ) ) ), 'items' => $items ); }
        } return $rows;
    }
    public static function sidebar( array $record ): string {
        $html = ''; foreach ( self::rows( $record ) as $key => $row ) {
            $items = array(); foreach ( $row['items'] as $item ) { $items[] = $item['url'] ? '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>' : esc_html( $item['title'] ); }
            $html .= '<div data-relationship="' . esc_attr( $key ) . '"><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . implode( ', ', $items ) . '</dd></div>';
        } return $html ? '<dl class="facts related-records">' . $html . '</dl>' : '';
    }
    /** Reverse links use effective fields and the same publication rules as the sidebar. */
    public static function related( array $entity ): array {
        $d = self::definitions()[ $entity['type'] ] ?? null; if ( ! $d || '' === trim( $entity['data']['title'] ?? '' ) ) { return array(); }
        $types = array_values( array_filter( $d[5], static fn( $type ) => Schema::enabled( $type ) ) ); if ( ! $types ) { return array(); }
        $query = array( 'relation' => 'OR', array( 'key' => 'msc_' . $d[3], 'value' => $entity['uuid'], 'compare' => 'LIKE' ), array( 'key' => 'msc_' . $d[4], 'value' => $entity['data']['title'] ?? '', 'compare' => 'LIKE' ) );
        $posts = get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => 100, 'meta_query' => $query, 'orderby' => 'title', 'order' => 'ASC' ) ); $out = array();
        foreach ( $posts as $post ) { $g = Plugin::store()->by_post( $post->ID ); $r = $g ? Presentation::record( $g['id'] ) : null; if ( ! $r ) { continue; } foreach ( self::rows( $r ) as $row ) { if ( in_array( $entity['uuid'], array_column( $row['items'], 'uuid' ), true ) ) { $out[] = $r; break; } } } return $out;
    }
}
