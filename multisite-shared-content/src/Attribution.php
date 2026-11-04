<?php
namespace MSC;

/** Credit only the values and assets actually displayed on this site. */
final class Attribution {
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
        $key = array( 'msc_person' => 'headshot', 'msc_movie' => 'poster', 'msc_show' => 'poster', 'msc_episode' => 'episode_image' )[ $record['type'] ] ?? 'featured_image';
        $ref = $record['data'][ $key ] ?? array(); if ( ! Media::url( (array) $ref ) ) { $ref = $record['data']['featured_image'] ?? array(); }
        return self::media_html( (array) $ref );
    }
    public static function media_html( array $ref ): string {
        if ( empty( $ref['site_id'] ) || empty( $ref['attachment_id'] ) ) { return ''; }
        switch_to_blog( (int) $ref['site_id'] );
        try { $source = get_post_meta( (int) $ref['attachment_id'], '_msc_open_source', true ); }
        finally { restore_current_blog(); }
        return is_array( $source ) ? '<div class="msc-image-credit"><small>Image: ' . self::source_html( $source ) . '</small></div>' : '';
    }
}
