<?php
/** Additive presentation content. Never rewrites an existing post or shared record. */
wp_set_current_user( 1 );
require_once __DIR__ . '/demo-art.php';
$headlines = array(
    'a' => array( 'The Last Meridian: First Look at the New Space Adventure', 'Harbor Nine Sets a Date for Its Mystery Series Premiere', 'Aegis Protocol Reveals Its First Teaser', 'The Last Meridian Cast Discusses Filming on Ice', 'Harbor Nine: A Guide to the Fictional Coastal Setting', 'New Streaming Releases: This Month’s Demo Schedule', 'Skyline Stories Announces a Second Season', 'The Last Meridian Adds a Digital Release Date', 'Harbor Nine Episode Guide: Season One', 'Aegis Protocol Shares a Production Update', 'Behind the Scenes of the Meridian Space Station', 'Five Original Films in Our Demo Release Calendar', 'How Harbor Nine Built Its Harbor Set', 'New Trailers and Features in the Demo Catalog' ),
    'b' => array( 'Aegis Protocol: The First Look at a New Hero', 'Crystal Guard Reveals Its Original Superhero Team', 'Harbor Nine Introduces a Mysterious New Character', 'Aegis Protocol’s Costume Design Explained', 'Crystal Guard: Meet the Fictional Guardians', 'New Superhero Releases on the Demo Calendar', 'The Aegis Protocol Teaser: Five Production Details', 'Crystal Guard Announces Its Next Chapter', 'How the Demo Team Created an Original Hero', 'Harbor Nine Expands Its Fictional Universe', 'Aegis Protocol: From Concept Art to Screen', 'Inside the Crystal Guard’s Mountain Headquarters', 'Upcoming Movie and TV Releases by Month', 'New Character Features in the Demo Catalog' ),
    'c' => array( 'Skyward Echoes Review: Exploring a World Above the Forest', 'Neon Circuit Review: Precision Racing After Dark', 'Quiet Summit Review: A Journey Through the Highlands', 'Studio Controller Review: A Fictional Hardware Showcase', 'Skyward Echoes: A Closer Look at Its Floating Cities', 'Neon Circuit: The Demo’s Racing Systems Explained', 'The Art of Building Original Game Worlds', 'How Skyward Echoes Uses Color to Guide Players', 'A Guide to the Demo’s Platform Categories', 'From Concept to Controller: Designing a Fictional Game', 'Skyward Echoes Gets Separate PC and PS5 Dates', 'Neon Circuit Announces Its Next Demo Update', 'This Month’s Video Game Release Calendar', 'Quiet Summit: Exploring the Mountain Trail' ),
    'd' => array( 'Quiet Summit Reveals Its PS5 Adventure', 'Skyward Echoes Sets Its PlayStation 5 Release Date', 'Neon Circuit Brings Night Racing to PS5', 'Studio Controller: A Fictional Accessory Preview', 'A Closer Look at Quiet Summit’s Mountain World', 'Sony Handheld Games Join the Demo Catalog', 'Upcoming PS5 Releases: This Month’s Demo Calendar', 'Quiet Summit: The Demo’s Exploration Systems', 'Skyward Echoes Details Its Console Release Window', 'Neon Circuit: A Guide to Its Fictional Race Tracks', 'Classic Handheld Showcase: A Vita Demo Adventure', 'How Platform-Specific Release Dates Work', 'New Features in the PlayStation Demo Catalog', 'The Art Direction Behind Quiet Summit' ),
);
$sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
    $key = \MSC\NetworkSites::key( $site->path ); if ( ! $key ) { continue; } $sites[ $key ] = (int) $site->blog_id;
    switch_to_blog( (int) $site->blog_id );
    try {
        $categories = array_values( array_filter( \MSC\NetworkSites::profiles()[ $key ]['nav'], static fn( $name ) => 'Home' !== $name ) );
        $terms = array(); foreach ( $categories as $name ) { $term = term_exists( sanitize_title( $name ), 'category' ); if ( ! $term ) { $term = wp_insert_term( $name, 'category', array( 'slug' => sanitize_title( $name ) ) ); } if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); } $terms[] = (int) ( is_array( $term ) ? $term['term_id'] : $term ); }
        foreach ( $headlines[ $key ] as $i => $headline ) {
            $slug = 'network-' . $key . '-' . $i;
            if ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_msc_network_demo_slug', 'meta_value' => $slug, 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) { continue; }
            $summary = 'A fictional editorial feature created to demonstrate this network site’s news layout, categories and release calendar.';
            $art = in_array( $key, array( 'a', 'b' ), true ) ? ( $i + ( 'b' === $key ? 2 : 0 ) ) % 4 : 4 + $i % 4;
            $art_names = array( 'Meridian' => 0, 'Harbor' => 1, 'Aegis' => 2, 'Crystal' => 3, 'Skyward' => 4, 'Neon Circuit' => 5, 'Quiet Summit' => 6, 'Controller' => 7 ); foreach ( $art_names as $needle => $tile ) { if ( str_contains( $headline, $needle ) ) { $art = $tile; break; } }
            $id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $headline, 'post_excerpt' => $summary, 'post_content' => '<p>' . esc_html( $summary ) . '</p><p>' . esc_html( $headline ) . ' is an original presentation scenario. The projects, release announcements and review scores in these sample articles are invented.</p><p>Use the catalog navigation to explore structured titles, or open the release calendar to see how exact dates appear across the network. News posts are local to this site, while catalog information can be shared between participating sites.</p>', 'post_category' => array( $terms[ $i % count( $terms ) ] ), 'menu_order' => $i, 'meta_input' => array( '_msc_network_demo_slug' => $slug, '_msc_demo_art' => $art, '_msc_review_score' => (string) ( 7 + $i % 3 ) ) ), true );
            if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        }
    } finally { restore_current_blog(); }
}
if ( count( $sites ) !== 4 ) { throw new RuntimeException( 'Run the four-site setup first.' ); }
$store = \MSC\Plugin::store(); $saved = get_network_option( get_current_network_id(), 'msc_network_demo_200', array() ); $new = array(); $month = wp_date( 'Y-m' ); $next = ( new DateTimeImmutable( $month . '-01' ) )->modify( '+1 month' )->format( 'Y-m' );
foreach ( array( 'person' => 'msc_person', 'movie' => 'msc_movie', 'show' => 'msc_show', 'episode' => 'msc_episode', 'skyward' => 'msc_game', 'circuit' => 'msc_game', 'summit' => 'msc_game', 'handheld' => 'msc_game', 'pc' => 'msc_game' ) as $key => $type ) {
    if ( ! empty( $saved[ $key ] ) && $store->get( $saved[ $key ] ) ) { continue; }
    switch_to_blog( $sites[ 'msc_game' === $type ? 'c' : 'a' ] );
    try {
        $data = array( 'title' => 'Demo entry', 'excerpt' => 'Fictional presentation title with shared catalog data.', 'content' => '<p>This is an original fictional title for the network presentation. Its release dates demonstrate the live calendar.</p>' );
        if ( 'person' === $key ) { $data += array( 'professions' => array( 'Actor', 'Director' ), 'headshot' => msc_demo_art( 'actor' ) ); $data['title'] = 'Alex Rowan (Demo)'; }
        elseif ( 'movie' === $key ) { $data += array( 'release_date' => $month . '-12', 'digital_release_date' => $next . '-05', 'genres' => array( 'Science Fiction', 'Adventure' ), 'poster' => msc_demo_art( 'movie' ), 'actors' => array( $saved['person'] ) ); $data['title'] = 'The Last Meridian'; }
        elseif ( 'show' === $key ) { $data += array( 'premiere_date' => $month . '-18', 'genres' => array( 'Mystery', 'Drama' ), 'poster' => msc_demo_art( 'show' ), 'number_of_seasons' => 1 ); $data['title'] = 'Harbor Nine'; }
        elseif ( 'episode' === $key ) { $data += array( 'parent_show' => $saved['show'], 'season_number' => 1, 'episode_number' => 1, 'release_date' => $month . '-18', 'episode_image' => msc_demo_art( 'episode' ) ); $data['title'] = 'The Arrival'; }
        else {
            $titles = array( 'skyward' => 'Skyward Echoes', 'circuit' => 'Neon Circuit', 'summit' => 'Quiet Summit', 'handheld' => 'Pocket Observatory', 'pc' => 'Desktop Frontier' ); $data['title'] = $titles[ $key ];
            $id = 'pc' === $key ? 6 : ( 'handheld' === $key ? 46 : 167 ); $platform = array( 6 => 'PC (Microsoft Windows)', 46 => 'PlayStation Vita', 167 => 'PlayStation 5' )[ $id ]; $day = array( 'skyward' => '10', 'circuit' => '14', 'summit' => '24', 'handheld' => '21', 'pc' => '27' )[ $key ];
            $releases = array( array( 'date' => $month . '-' . $day, 'precision' => 'day', 'display' => $month . '-' . $day, 'platform_id' => $id, 'platform' => $platform, 'region' => 'Worldwide' ) );
            if ( 'skyward' === $key ) { $releases[] = array( 'date' => $next . '-03', 'precision' => 'day', 'display' => $next . '-03', 'platform_id' => 6, 'platform' => 'PC (Microsoft Windows)', 'region' => 'Worldwide' ); }
            $data += array( 'release_date' => $month . '-' . $day, 'release_dates' => $releases, 'platform_ids' => 'skyward' === $key ? array( 167, 6 ) : array( $id ), 'platforms' => 'skyward' === $key ? array( 'PlayStation 5', 'PC (Microsoft Windows)' ) : array( $platform ), 'developers' => array( 'Demo Studio' ), 'publishers' => array( 'Network Demo Games' ), 'genres' => array( 'Adventure' ), 'game_modes' => array( 'Single player' ), 'poster' => msc_demo_art( 'skyward' === $key ? 'show' : 'movie' ) );
        }
        $r = $store->create( $type, $data ); $saved[ $key ] = $r['uuid']; $new[] = $r['uuid']; update_network_option( get_current_network_id(), 'msc_network_demo_200', $saved );
    } finally { restore_current_blog(); }
}
for ( $i = 0; $i < 200 && \MSC\Queue::status()['pending']; ++$i ) { $result = \MSC\Queue::run( 1000 ); if ( $result['errors'] || ! $result['processed'] ) { throw new RuntimeException( 'Presentation synchronization failed.' ); } }
foreach ( $sites as $site ) { switch_to_blog( $site ); try { foreach ( $new as $uuid ) { $r = $store->resolved( $uuid ); if ( \MSC\Schema::enabled( $r['type'] ) && \MSC\GamePolicy::accepts( $r ) && $r['mapping'] ) { $store->local( $uuid, array(), array(), (int) $r['mapping']['local_version'], 'publish' ); } } } finally { restore_current_blog(); } }
WP_CLI::success( 'Four branded homepages, fictional news, catalog examples and release calendars are ready. Existing edits were retained.' );
