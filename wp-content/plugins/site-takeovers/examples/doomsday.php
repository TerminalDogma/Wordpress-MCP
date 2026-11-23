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
    $campaign = (int) ( $existing[0]->ID ?? 0 );
    $settings = $campaign ? Takeovers::read( $campaign ) : array();

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $art = array();
    $assets = array( 'banner' => array( 'doomsday-banner.webp', 'Top banner' ), 'left' => array( 'doomsday-rail.webp', 'Side ads' ),
        'background' => array( 'doomsday-wallpaper.webp', 'Page background' ), 'logo' => array( 'doomsday-logo.webp', 'Official Marvel Studios logo' ) );
    foreach ( $assets as $slot => list( $name, $label ) ) {
        $path = __DIR__ . '/' . $name;
        if ( ! is_readable( $path ) ) { throw new RuntimeException( 'The bundled Doomsday artwork is missing.' ); }
        $media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sat_demo_asset', 'meta_value' => $name ) );
        if ( $media && is_file( (string) get_attached_file( $media[0] ) ) ) { $art[ $slot ] = (int) $media[0]; continue; }

        $upload = wp_upload_bits( $name, null, file_get_contents( $path ) );
        if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
        $id = wp_insert_attachment( array(
            'post_title' => 'Avengers Doomsday demo — ' . $label,
            'post_mime_type' => 'image/webp', 'post_status' => 'inherit',
            'post_content' => 'logo' === $slot ? 'Marvel Studios artwork from https://www.marvel.com/movies/avengers-doomsday' : '',
            'meta_input' => array( '_sat_demo_asset' => $name, '_wp_attachment_image_alt' => 'logo' === $slot ? 'Marvel Studios Avengers: Doomsday' : 'Emerald and brass Doomsday concept artwork' ),
        ), $upload['file'], 0, true );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $upload['file'] );
            throw new RuntimeException( $id->get_error_message() );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
        $art[ $slot ] = $id;
    }
    $art['right'] = $art['left'];

    $store = SharedContent::store();
    $target = 0;
    $movies = get_posts( array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 'title' => 'Avengers: Doomsday', 'numberposts' => 5 ) );
    foreach ( $movies as $movie ) {
        if ( 'avengers-doomsday-takeover-preview' === $movie->post_name ) { continue; }
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
                'genres' => array( 'action film', 'adventure film', 'science fiction film' ),
                'release_date' => '2026-12-18',
                'poster' => array( 'site_id' => $site, 'attachment_id' => $art['left'] ),
            ) );
        }
        SharedContent::materializer()->sync( (int) $record['id'], $site );
        $mapping = $store->mapping( (int) $record['id'] );
        if ( empty( $mapping['post_id'] ) ) { throw new RuntimeException( 'The local movie preview could not be created.' ); }
        if ( 'draft' === $mapping['status'] ) { $store->local( $record['uuid'], array(), array(), (int) $mapping['local_version'], 'publish' ); }
        $target = (int) $mapping['post_id'];
    }

    if ( ! $campaign ) {
        $campaign = wp_insert_post( array(
            'post_type' => Takeovers::TYPE, 'post_status' => 'publish', 'post_title' => 'Avengers: Doomsday takeover demo',
            'meta_input' => array( '_sat_doomsday_demo' => '1' ),
        ), true );
        if ( is_wp_error( $campaign ) ) { throw new RuntimeException( $campaign->get_error_message() ); }
    }
    $settings += array(
        'scope' => 'pages', 'brand' => 'Marvel Studios', 'headline' => 'Avengers: Doomsday',
        'message' => 'In theaters December 18', 'cta' => 'Explore the film',
        'destination' => 'https://www.marvel.com/movies/avengers-doomsday',
        'colors' => array( 'background' => '#0b1610', 'surface' => '#f0f3e8', 'text' => '#17281e', 'accent' => '#3a633c', 'header' => '#162d20' ),
        'art' => array(), 'targets' => array(),
    );
    $targets = array_filter( $settings['targets'], static fn( $id ) => 'avengers-doomsday-takeover-preview' !== get_post_field( 'post_name', $id ) );
    $settings['targets'] = implode( "\n", array_unique( array( $target, ...$targets ) ) );
    $settings['art'] += $art;
    Takeovers::save( $campaign, $settings );
    $credits = require __DIR__ . '/doomsday-credits.php';
    $credits( $store->by_post( $target ), $site );
    WP_CLI::success( 'Doomsday takeover #' . $campaign . ' — ' . get_permalink( $target ) );
} );
