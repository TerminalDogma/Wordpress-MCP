<?php
/** Install the bundled demo artwork in the current site's Media Library. */
return static function ( string $title, array $files ): array {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $art = array();
    foreach ( $files as $slot => $file ) {
        list( $name, $label ) = $file;
        $path = __DIR__ . '/' . $name;
        if ( ! is_readable( $path ) ) { throw new RuntimeException( 'Missing bundled artwork: ' . $name ); }
        $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sat_demo_asset', 'meta_value' => $name ) );
        if ( $existing && is_file( (string) get_attached_file( $existing[0] ) ) ) {
            $art[ $slot ] = (int) $existing[0];
            continue;
        }
        $bytes = file_get_contents( $path );
        if ( ! $bytes || 'image/webp' !== ( getimagesizefromstring( $bytes )['mime'] ?? '' ) ) { throw new RuntimeException( 'Invalid artwork: ' . $name ); }
        $upload = wp_upload_bits( $name, null, $bytes );
        if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
        $meta = array( '_sat_demo_asset' => $name, '_wp_attachment_image_alt' => $title . ' — ' . $label );
        if ( ! empty( $file[2] ) ) { $meta['_msc_manual_image_source'] = $file[2]; }
        $id = wp_insert_attachment( wp_slash( array(
            'post_title' => $title . ' — ' . $label, 'post_mime_type' => 'image/webp', 'post_status' => 'inherit', 'meta_input' => $meta,
        ) ), $upload['file'], 0, true );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $upload['file'] );
            throw new RuntimeException( $id->get_error_message() );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
        $art[ $slot ] = (int) $id;
    }
    return $art;
};
