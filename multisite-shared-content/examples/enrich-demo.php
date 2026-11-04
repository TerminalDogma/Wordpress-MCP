<?php
/** Add people, categories, and original art without replacing saved demo edits. */
require_once __DIR__ . '/demo-art.php';
switch_to_blog( $sites[0] );
try {
    foreach ( array( 'actor' => array( 'Maya Chen', 'Actor', 'A fictional performer drawn to intimate stories set against extraordinary landscapes.' ), 'director' => array( 'Julian Vale', 'Director', 'A fictional filmmaker exploring memory, distance, and the places we call home.' ) ) as $key => $info ) {
        if ( empty( $demo[ $key ] ) || ! $store->get( $demo[ $key ] ) ) {
            $person = $store->create( 'msc_person', array( 'title' => $info[0], 'content' => '<p>' . $info[2] . '</p>', 'excerpt' => $info[2], 'professions' => array( $info[1] ), 'country' => 'Canada', 'headshot' => msc_demo_art( $key ) ) ); $demo[ $key ] = $person['uuid'];
        }
    }
    $additions = array(
        'movie' => array( 'excerpt' => 'At the edge of a disappearing world, a lighthouse keeper follows a signal that should have gone silent years ago.', 'genres' => array( 'Science Fiction', 'Drama' ), 'poster' => msc_demo_art( 'movie' ), 'promotional_images' => array( msc_demo_art( 'episode' ) ), 'actors' => array( $demo['actor'] ), 'directors' => array( $demo['director'] ), 'country' => 'Canada', 'language' => 'English', 'content_rating' => 'PG-13', 'release_status' => 'Released' ),
        'show' => array( 'excerpt' => 'An isolated research station. A message from nowhere. Some mysteries are better left unanswered.', 'genres' => array( 'Mystery', 'Science Fiction' ), 'poster' => msc_demo_art( 'show' ), 'actors' => array( $demo['actor'] ), 'creators' => array( $demo['director'] ), 'number_of_seasons' => 1, 'number_of_episodes' => 1, 'country' => 'Canada', 'language' => 'English', 'status' => 'Returning Series' ),
        'episode' => array( 'excerpt' => 'A routine night shift becomes a first encounter when the station picks up an impossible transmission.', 'content' => '<p>As a winter storm closes in, the team detects a repeating signal beneath the ice. Maya must decide whether to follow procedure or trust an instinct that could change everything.</p><p>This fictional pilot introduces the station, its crew, and the question that will bring them together.</p>', 'episode_image' => msc_demo_art( 'episode' ), 'actors' => array( $demo['actor'] ), 'directors' => array( $demo['director'] ), 'runtime' => 48 ),
    );
    foreach ( $additions as $key => $values ) {
        $r = $store->get( $demo[ $key ] ); $missing = array_diff_key( $values, $r['data'] );
        if ( $missing ) { $store->update( $r['uuid'], $missing, (int) $r['version'] ); }
    }
    update_network_option( get_current_network_id(), 'msc_demo_records', $demo );
} finally { restore_current_blog(); }
for ( $i = 0; $i < 100 && \MSC\Queue::status()['pending']; ++$i ) { $result = \MSC\Queue::run( 1000 ); if ( $result['errors'] ) { throw new RuntimeException( 'People demo synchronization failed.' ); } }
foreach ( $sites as $site ) {
    switch_to_blog( $site );
    try { foreach ( array( 'actor', 'director' ) as $key ) { $r = $store->resolved( $demo[ $key ] ); if ( 'publish' !== $r['mapping']['status'] ) { $store->local( $r['uuid'], array(), array(), (int) $r['mapping']['local_version'], 'publish' ); } } }
    finally { restore_current_blog(); }
}
