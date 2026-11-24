<?php
/** Install the local A New Hope movie refresh and scheduled anniversary takeover. */
use MSC\{Database, Plugin as SharedContent, Presentation, Schema};
use SiteTakeovers\Plugin as Takeovers;

if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) || ! class_exists( SharedContent::class ) || ! Schema::enabled( 'msc_movie' ) ) {
    WP_CLI::error( 'Run this example as a site administrator on a local site with Movies enabled.' );
}

$site = get_current_blog_id();
Database::lock( 'star-wars-50-takeover-example-' . $site, static function () use ( $site ): void {
    $official = 'https://www.starwars.com/films/star-wars-episode-iv-a-new-hope';
    $announcement = 'https://www.starwars.com/news/star-wars-50th-anniversary-theatrical-release';
    $lucasfilm = array( 'source_page' => $official, 'credit' => 'Lucasfilm' );
    $install_art = require __DIR__ . '/demo-art.php';
    $art = $install_art( 'Star Wars: A New Hope', array(
        'background' => array( 'star-wars-wallpaper.webp', 'Desert planetscape' ),
        'left' => array( 'star-wars-left.webp', 'Starship side ad' ),
        'right' => array( 'star-wars-right.webp', 'Twin suns side ad' ),
        'logo' => array( 'star-wars-logo.webp', 'Official Star Wars 50th anniversary logo', array( 'source_page' => 'https://www.starwars.com/star-wars-50', 'credit' => 'Lucasfilm' ) ),
        'poster' => array( 'star-wars-poster.webp', 'Film poster', $lucasfilm ),
    ) );
    $art['banner'] = $art['background'];

    $store = SharedContent::store();
    $preview_slug = 'star-wars-a-new-hope-takeover-preview';
    $slugs = array( 'star-wars-episode-iv-a-new-hope', 'star-wars-a-new-hope', 'star-wars-1977', 'star-wars', 'a-new-hope' );
    $movie = null;
    $candidates = get_posts( array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 'post_name__in' => $slugs, 'numberposts' => -1 ) );
    $candidates = array_merge( $candidates, get_posts( array( 'post_type' => 'msc_movie', 'post_status' => 'publish', 's' => 'A New Hope', 'numberposts' => 30 ) ) );
    foreach ( $candidates as $candidate ) {
        if ( $candidate->post_password || $preview_slug === $candidate->post_name ) { continue; }
        $record = $store->by_post( $candidate->ID );
        $current = $record ? Presentation::record( $record['id'] ) : null;
        if ( ! $current ) { continue; }
        $external = $current['data']['external_ids'] ?? array();
        $identity = sanitize_title( $current['data']['title'] );
        if ( 'tt0076759' === ( $external['imdb'] ?? '' ) || 'Q17738' === ( $external['wikidata'] ?? '' ) || ( empty( $external['imdb'] ) && in_array( $identity, $slugs, true ) && '1977' === substr( $current['data']['release_date'] ?? '', 0, 4 ) ) ) {
            $movie = $record;
            break;
        }
    }

    $overview = '<p>Luke Skywalker leaves Tatooine with Obi-Wan Kenobi after a message from Princess Leia draws him into the fight against the Galactic Empire. With Han Solo, Chewbacca and two droids, he joins the Rebel Alliance on a mission to rescue Leia and stop the Death Star.</p>';
    $poster = array( 'site_id' => $site, 'attachment_id' => $art['poster'] );
    $featured = array( 'site_id' => $site, 'attachment_id' => $art['background'] );
    $defaults = array( 'release_date' => '1977-05-25', 'runtime' => 121, 'content_rating' => 'PG', 'content' => $overview, 'poster' => $poster, 'featured_image' => $featured );
    if ( ! $movie ) {
        $preview = get_page_by_path( $preview_slug, OBJECT, 'msc_movie' );
        $movie = $preview ? $store->by_post( $preview->ID ) : null;
        $movie = $movie ?: $store->create( 'msc_movie', array( 'title' => 'Star Wars: A New Hope', 'slug' => $preview_slug,
            'genres' => array( 'space opera', 'science fiction film', 'action film', 'adventure film', 'coming-of-age film' ),
        ) + $defaults );
        SharedContent::materializer()->sync( (int) $movie['id'], $site );
        $mapping = $store->mapping( (int) $movie['id'] );
        if ( empty( $mapping['post_id'] ) ) { throw new RuntimeException( 'The local A New Hope movie could not be created.' ); }
        if ( 'draft' === $mapping['status'] ) { $store->local( $movie['uuid'], array(), array(), (int) $mapping['local_version'], 'publish' ); }
    }

    $film = $store->resolved( $movie['id'] );
    $target = (int) $film['mapping']['post_id'];
    if ( ! Presentation::record( $movie['id'] ) ) { throw new RuntimeException( 'The selected movie is unavailable for public campaign placement.' ); }
    $data = $film['data'];
    $values = array_filter( $defaults, static fn( $value, $key ) => empty( $data[ $key ] ), ARRAY_FILTER_USE_BOTH );
    // The disposable training suite seeded this title with a synthetic overview, artwork and credits.
    if ( str_starts_with( wp_strip_all_tags( $data['content'] ?? '' ), 'This is a synthetic, fully described catalog integration fixture' ) ) {
        $values += array( 'content' => $overview, 'excerpt' => '', 'poster' => $poster, 'featured_image' => $featured );
    }
    foreach ( array( 'actors', 'directors', 'screenwriters', 'producers', 'cinematographers', 'film_editors', 'composers', 'production_company_records' ) as $field ) {
        $current = $data[ $field ] ?? array();
        $real = array_values( array_filter( $current, static function ( string $uuid ) use ( $store ): bool {
            $record = $store->get( $uuid );
            return ! in_array( $record['data']['external_ids']['wikidata'] ?? '', array( 'Q990000001', 'Q990000002', 'Q990000003' ), true );
        } ) );
        if ( $real !== $current ) { $values[ $field ] = $real; }
    }
    $row = array( 'date' => '2027-02-19', 'label' => '50th anniversary theatrical re-release', 'url' => $announcement );
    $history = (array) ( $data['release_history'] ?? array() );
    if ( ! in_array( $row['date'], array_column( array_merge( $history, (array) ( $data['additional_release_dates'] ?? array() ) ), 'date' ), true ) ) {
        $values['release_history'] = array_merge( $history, array( $row ) );
    }
    $credits = require __DIR__ . '/catalog-credits.php';
    $credits( $movie, $site, 'star-wars', $values );

    $campaigns = get_posts( array( 'post_type' => Takeovers::TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
        'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sat_star_wars_50_demo', 'meta_value' => '1' ) );
    $campaign = (int) ( $campaigns[0] ?? 0 );
    if ( ! $campaign ) {
        $campaign = wp_insert_post( array( 'post_type' => Takeovers::TYPE, 'post_status' => 'publish', 'post_title' => 'Star Wars: A New Hope — 50th anniversary re-release', 'meta_input' => array( '_sat_star_wars_50_demo' => '1' ) ), true );
        if ( is_wp_error( $campaign ) ) { throw new RuntimeException( $campaign->get_error_message() ); }
    }
    if ( ! Takeovers::read( $campaign ) ) {
        Takeovers::save( $campaign, array(
            'scope' => 'pages', 'targets' => (string) $target,
            'headline' => 'Star Wars: A New Hope — 50th anniversary',
            'tagline' => 'A long time ago. A galaxy far, far away. A theater near you.',
            'message' => 'Back in theaters February 19, 2027', 'cta' => 'Explore the re-release', 'destination' => $announcement,
            'window' => array( 'start' => '2027-02-12T00:00', 'end' => '2027-02-22T00:00' ),
            'colors' => array( 'background' => '#090f20', 'surface' => '#f7f0e3', 'text' => '#15203a', 'accent' => '#95420a', 'header' => '#131d39' ),
            'art' => $art,
        ) );
    }
    WP_CLI::log( 'Edit or preview the campaign: ' . admin_url( 'post.php?post=' . $campaign . '&action=edit' ) );
    WP_CLI::success( 'A New Hope movie — ' . get_permalink( $target ) );
} );
