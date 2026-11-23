<?php
/** Attach the bundled licensed portraits and publish Iron Man’s linked drafts on the preview site. */
use MSC\{Database, MovieEditorial, PersonIdentity, Plugin, Presentation, Schema};

if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_network_options' ) || ! current_user_can( 'upload_files' ) || ! Schema::enabled( 'msc_person' ) ) {
    WP_CLI::error( 'Run this command as a network administrator on a local site with People enabled.' );
}

$movie_post = get_page_by_path( 'iron-man', OBJECT, 'msc_movie' );
$store = Plugin::store();
$movie = $movie_post ? $store->by_post( $movie_post->ID ) : null;
if ( ! $movie || 'active' !== $movie['state'] ) {
    WP_CLI::error( 'The Iron Man movie entry is missing from this site.' );
}

$folder = __DIR__ . '/iron-man-portraits';
if ( ! is_readable( $folder . '/sources.json' ) ) { WP_CLI::error( 'The bundled portrait sources are unavailable.' ); }
$manifest = json_decode( file_get_contents( $folder . '/sources.json' ), true, 32, JSON_THROW_ON_ERROR );
$portraits = array();
foreach ( $manifest as $name => $source ) {
    $portraits[ PersonIdentity::name( $name ) ] = $source;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
$site = get_current_blog_id();
Database::lock( 'iron-man-editorial-' . $site, static function () use ( $site, $store, $movie, $portraits, $folder ): void {
    $film = $store->resolved( $movie['id'] );
    $ids = array();
    foreach ( array( 'actors', 'directors', 'screenwriters', 'producers', ...array_keys( MovieEditorial::CREW ) ) as $field ) {
        foreach ( $film['data'][ $field ] ?? array() as $uuid ) { $ids[ $uuid ] = true; }
    }
    if ( ! $ids ) { WP_CLI::error( 'The Iron Man movie entry has no linked cast or crew.' ); }

    $added = $published = $read = 0;
    $missing = $failed = array();
    foreach ( array_keys( $ids ) as $uuid ) {
        $person = $store->resolved( $uuid );
        if ( ! $person || 'msc_person' !== $person['type'] || 'active' !== $person['state'] ) { continue; }
        $name = $person['data']['title'];
        try {
            $mapping = $person['mapping'];
            if ( empty( $mapping['post_id'] ) && empty( $mapping['suppressed'] ) ) {
                Plugin::materializer()->sync( (int) $person['id'], $site );
                $person = $store->resolved( $uuid );
                $mapping = $person['mapping'];
            }
            if ( empty( $mapping['post_id'] ) || ! empty( $mapping['suppressed'] ) ) { continue; }
            ++$read;
            $values = array();
            if ( empty( Presentation::artwork( $person )['url'] ) ) {
                $portrait = $portraits[ PersonIdentity::name( $name ) ] ?? null;
                if ( $portrait ) {
                    $path = $folder . '/' . $portrait['file'];
                    $media = get_posts( array(
                        'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
                        'meta_key' => '_msc_iron_man_portrait', 'meta_value' => $portrait['file'],
                    ) );
                    $attachment = (int) ( $media[0] ?? 0 );
                    if ( ! $attachment || ! is_file( (string) get_attached_file( $attachment ) ) ) {
                        $bytes = file_get_contents( $path );
                        if ( ! $bytes || 'image/jpeg' !== ( getimagesizefromstring( $bytes )['mime'] ?? '' ) ) {
                            throw new RuntimeException( 'The bundled portrait is not a valid JPEG.' );
                        }
                        $upload = wp_upload_bits( $portrait['file'], null, $bytes );
                        if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
                        $id = wp_insert_attachment( wp_slash( array(
                            'post_title' => $name . ' portrait', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg',
                            'meta_input' => array(
                                '_msc_iron_man_portrait' => $portrait['file'],
                                '_wp_attachment_image_alt' => $name,
                                '_msc_open_source' => $portrait['source'],
                            ),
                        ) ), $upload['file'], 0, true );
                        if ( is_wp_error( $id ) ) {
                            wp_delete_file( $upload['file'] );
                            throw new RuntimeException( $id->get_error_message() );
                        }
                        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
                        $attachment = (int) $id;
                    }
                    $values['headshot'] = array( 'site_id' => $site, 'attachment_id' => $attachment );
                } else {
                    $missing[] = $name;
                }
            }
            $status = 'draft' === ( $mapping['status'] ?? '' ) ? 'publish' : null;
            if ( $values || $status ) {
                $saved = $store->local( $uuid, $values, array(), (int) $mapping['local_version'], $status );
                if ( $values && ! empty( Presentation::artwork( $saved )['url'] ) ) { ++$added; }
                if ( $status && 'publish' === get_post_status( (int) $saved['mapping']['post_id'] ) ) { ++$published; }
            }
        } catch ( Throwable $error ) {
            $failed[] = $name;
            WP_CLI::warning( $name . ': ' . $error->getMessage() );
        }
    }
    WP_CLI::log( sprintf( '%d linked profiles checked. Added %d portraits and published %d drafts.', $read, $added, $published ) );
    if ( $missing ) { WP_CLI::warning( 'Portraits still needed for ' . implode( ', ', $missing ) . '.' ); }
    if ( $failed ) { WP_CLI::error( 'Some profiles could not be updated. Retry after resolving the reported errors.' ); }
} );
