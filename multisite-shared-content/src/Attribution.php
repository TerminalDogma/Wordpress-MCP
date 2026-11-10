<?php
namespace MSC;

/** Credit only the values and assets actually displayed on this site. */
final class Attribution {
    private static array $footer = array();
    public static function collect_html( string $label, string $html ): string { if ( $html ) { self::$footer[ OpenImport::hash( array( $label, $html ) ) ] = array( 'label' => $label, 'html' => $html ); } return ''; }
    public static function collect( array $record, ?array $fields = null ): string { return self::collect_html( (string) ( $record['data']['title'] ?? 'Content' ), self::render( $record, $fields ) ); }
    public static function collect_image( array $record ): string { return self::collect_html( ( $record['data']['title'] ?? 'Content' ) . ' — image', self::image_html( $record ) ); }
    public static function collect_media( array $ref, string $title ): string { return self::collect_html( $title . ' — gallery image', self::media_html( $ref ) ); }
    public static function footer(): string {
        $igdb = '';
        if ( Schema::enabled( 'msc_game' ) ) { global $wpdb; $records = Database::table( 'records' ); $maps = Database::table( 'maps' ); $has = $wpdb->get_var( $wpdb->prepare( "SELECT r.id FROM $records r INNER JOIN $maps m ON m.record_id=r.id AND m.site_id=%d INNER JOIN $wpdb->posts p ON p.ID=m.post_id WHERE r.type='msc_game' AND r.state='active' AND m.status='publish' AND m.suppressed=0 AND p.post_status='publish' AND JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.external_ids.igdb')) IS NOT NULL LIMIT 1", get_current_blog_id() ) ); if ( $has ) { $igdb = '<p class="msc-igdb-credit">Video game data: <a href="https://www.igdb.com/" rel="license noopener">IGDB</a></p>'; } }
        if ( ! self::$footer ) { return $igdb; } $html = $igdb . '<section class="msc-footer-credits"><details><summary>Sources and image credits</summary><ul>';
        foreach ( self::$footer as $credit ) { $html .= '<li><strong>' . esc_html( $credit['label'] ) . '</strong>' . $credit['html'] . '</li>'; } return $html . '</ul></details></section>';
    }
    public static function source_html( array $source ): string {
        $html = '<span class="msc-source"><a href="' . esc_url( $source['url'] ?? '' ) . '" rel="license noopener">' . esc_html( $source['attribution'] ?? $source['provider'] ?? 'Source' ) . '</a>';
        if ( ! empty( $source['credit'] ) ) { $html .= ' · ' . esc_html( $source['credit'] ); }
        $html .= ' · <a href="' . esc_url( $source['license_url'] ?? '' ) . '" rel="license">' . esc_html( $source['license'] ?? '' ) . '</a>';
        if ( ! empty( $source['changes'] ) ) { $html .= ' · ' . esc_html( $source['changes'] ); }
        return $html . '</span>';
    }
    public static function render( array $record, ?array $fields = null ): string {
        $global = Plugin::store()->get( $record['id'] ); $provenance = $global['data']['additional_metadata']['open_import']['fields'] ?? array(); $sources = array();
        foreach ( $provenance as $field => $entry ) {
            if ( $fields && ! in_array( $field, $fields, true ) || ! array_key_exists( $field, $record['data'] ) || ! is_array( $entry ) || ! isset( $entry['hash'], $entry['source'] ) || $entry['hash'] !== OpenImport::hash( $record['data'][ $field ] ) ) { continue; }
            $source = $entry['source']; $sources[ OpenImport::hash( $source ) ] = $source;
        }
        return $sources ? '<div class="msc-attribution"><small>Sources: ' . implode( '<br>', array_map( array( self::class, 'source_html' ), array_values( $sources ) ) ) . '</small></div>' : '';
    }
    public static function image_html( array $record ): string {
        $key = array( 'msc_person' => 'headshot', 'msc_movie' => 'poster', 'msc_show' => 'poster', 'msc_episode' => 'episode_image', 'msc_game' => 'poster', 'msc_comic' => 'poster', 'msc_comic_issue' => 'poster', 'msc_comic_arc' => 'poster' )[ $record['type'] ] ?? ( isset( EntityCatalog::definitions()[ $record['type'] ] ) ? 'logo' : 'featured_image' );
        $ref = $record['data'][ $key ] ?? array(); if ( ! Media::url( (array) $ref ) ) { $ref = $record['data']['featured_image'] ?? array(); }
        return self::media_html( (array) $ref );
    }
    public static function media_html( array $ref ): string {
        if ( empty( $ref['site_id'] ) || empty( $ref['attachment_id'] ) ) { return ''; }
        switch_to_blog( (int) $ref['site_id'] );
		try { $source = get_post_meta( (int) $ref['attachment_id'], '_msc_open_source', true ); $manual = get_post_meta( (int) $ref['attachment_id'], '_msc_manual_image_source', true ); }
        finally { restore_current_blog(); }
		if ( is_array( $manual ) && ( ! empty( $manual['source_page'] ) || ! empty( $manual['credit'] ) ) ) { $parts = array(); if ( ! empty( $manual['source_page'] ) ) { $parts[] = '<a href="' . esc_url( $manual['source_page'] ) . '" rel="noopener">Image source</a>'; } if ( ! empty( $manual['credit'] ) ) { $parts[] = esc_html( $manual['credit'] ); } return '<div class="msc-image-credit"><small>Image: ' . implode( ' · ', $parts ) . '</small></div>'; }
        return is_array( $source ) ? '<div class="msc-image-credit"><small>Image: ' . self::source_html( $source ) . '</small></div>' : '';
    }
}
