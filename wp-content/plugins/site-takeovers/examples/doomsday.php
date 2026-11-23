<?php
/** Create the local, page-only Doomsday takeover example. */
use MSC\{Database, Plugin as SharedContent, Presentation, Schema};
use SiteTakeovers\Plugin as Takeovers;

if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) || ! class_exists( SharedContent::class ) || ! Schema::enabled( 'msc_movie' ) ) {
    WP_CLI::error( 'Run this example as a site administrator on a local site with Movies enabled.' );
}

$site = get_current_blog_id();
Database::lock( 'doomsday-takeover-example-' . $site, static function () use ( $site ): void {
    $existing = get_posts( array(
        'post_type' => Takeovers::TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
        'numberposts' => 1, 'meta_key' => '_sat_doomsday_demo', 'meta_value' => '1',
    ) );
    if ( $existing ) {
        $settings = Takeovers::read( (int) $existing[0]->ID );
        $target = $settings['targets'][0] ?? 0;
        WP_CLI::success( 'Existing Doomsday campaign #' . $existing[0]->ID . ( $target ? ' — ' . get_permalink( $target ) : '' ) );
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $art = array();
    foreach ( array( 'doomsday-banner.webp' => 'Top banner', 'doomsday-rail.webp' => 'Side ads' ) as $name => $label ) {
        $path = __DIR__ . '/' . $name;
        if ( ! is_readable( $path ) ) { throw new RuntimeException( 'The bundled Doomsday artwork is missing.' ); }
        $media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sat_demo_asset', 'meta_value' => $name ) );
        if ( $media && is_file( (string) get_attached_file( $media[0] ) ) ) { $art[ $name ] = (int) $media[0]; continue; }

        $upload = wp_upload_bits( $name, null, file_get_contents( $path ) );
        if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
        $id = wp_insert_attachment( array(
            'post_title' => 'Avengers Doomsday demo — ' . $label,
            'post_mime_type' => 'image/webp', 'post_status' => 'inherit',
            'meta_input' => array( '_sat_demo_asset' => $name, '_wp_attachment_image_alt' => 'Emerald and brass Doomsday concept artwork' ),
        ), $upload['file'], 0, true );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $upload['file'] );
            throw new RuntimeException( $id->get_error_message() );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
        $art[ $name ] = $id;
    }

    $store = SharedContent::store();
    $target = 0;
    $movies = get_posts( array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 'title' => 'Avengers: Doomsday', 'numberposts' => 5 ) );
    foreach ( $movies as $movie ) {
        $global = $store->by_post( $movie->ID );
        if ( $global && ! $movie->post_password && Presentation::record( $global['id'] ) ) { $target = (int) $movie->ID; break; }
    }
    if ( ! $target ) {
        $preview = get_page_by_path( 'avengers-doomsday-takeover-preview', OBJECT, 'msc_movie' );
        $record = $preview ? $store->by_post( $preview->ID ) : null;
        if ( ! $record ) {
            $record = $store->create( 'msc_movie', array(
                'title' => 'Avengers: Doomsday',
                'slug' => 'avengers-doomsday-takeover-preview',
                'content' => '<p>Doctor Doom draws the Avengers into a conflict across realities, bringing them together with the Fantastic Four, the X-Men and heroes from Wakanda.</p>',
                'genres' => array( 'Action', 'Adventure' ),
                'poster' => array( 'site_id' => $site, 'attachment_id' => $art['doomsday-rail.webp'] ),
            ) );
        }
        SharedContent::materializer()->sync( (int) $record['id'], $site );
        $mapping = $store->mapping( (int) $record['id'] );
        if ( empty( $mapping['post_id'] ) ) { throw new RuntimeException( 'The local movie preview could not be created.' ); }
        if ( 'draft' === $mapping['status'] ) { $store->local( $record['uuid'], array(), array(), (int) $mapping['local_version'], 'publish' ); }
        $target = (int) $mapping['post_id'];
    }

    $campaign = wp_insert_post( array(
        'post_type' => Takeovers::TYPE, 'post_status' => 'publish', 'post_title' => 'Avengers: Doomsday takeover demo',
        'meta_input' => array( '_sat_doomsday_demo' => '1' ),
    ), true );
    if ( is_wp_error( $campaign ) ) { throw new RuntimeException( $campaign->get_error_message() ); }
    Takeovers::save( $campaign, array(
        'scope' => 'pages', 'targets' => (string) $target,
        'brand' => 'Marvel Studios', 'headline' => 'Avengers: Doomsday',
        'message' => 'Coming to theaters', 'cta' => 'Explore the film',
        'destination' => 'https://www.marvel.com/movies/avengers-doomsday',
        'colors' => array( 'background' => '#0b1610', 'surface' => '#f0f3e8', 'text' => '#17281e', 'accent' => '#3a633c', 'header' => '#162d20' ),
        'art' => array( 'banner' => $art['doomsday-banner.webp'], 'left' => $art['doomsday-rail.webp'], 'right' => $art['doomsday-rail.webp'] ),
    ) );
    WP_CLI::success( 'Doomsday takeover #' . $campaign . ' — ' . get_permalink( $target ) );
} );
