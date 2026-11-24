<?php
/** Undo the exact genre changes shipped with earlier takeover demos. */
use MSC\{Plugin, Propagation};

if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_network_options' ) ) {
    WP_CLI::error( 'Run this command as a network administrator on a local network.' );
}

$examples = array(
    'avengers-doomsday-takeover-preview' => array( array( 'action film', 'adventure film', 'science fiction film' ), array( 'Action', 'Adventure' ) ),
    'star-wars-a-new-hope-takeover-preview' => array( array( 'Action', 'Adventure', 'Science Fiction' ), array( 'space opera', 'science fiction film', 'action film', 'adventure film', 'coming-of-age film' ) ),
);
$store = Plugin::store();
foreach ( $examples as $slug => list( $changed, $original ) ) {
    $post = get_page_by_path( $slug, OBJECT, 'msc_movie' );
    $record = $post ? $store->by_post( $post->ID ) : null;
    if ( ! $record || $slug !== ( $record['data']['slug'] ?? '' ) ) { continue; }
    if ( $changed === ( $record['data']['genres'] ?? array() ) ) {
        $record = $store->update( $record['uuid'], array( 'genres' => $original ), (int) $record['version'] );
        Propagation::send( $record );
        WP_CLI::log( 'Restored the shared genres for ' . $record['data']['title'] . '.' );
    }
    $resolved = $store->resolved( $record['uuid'] );
    if ( $changed === ( $resolved['overrides']['genres'] ?? null ) ) {
        $store->local( $record['uuid'], array(), array( 'genres' ), (int) $resolved['mapping']['local_version'] );
        WP_CLI::log( 'Restored the inherited genres on this site for ' . $record['data']['title'] . '.' );
    }
}

// The original Star Wars installer also supplied the short genre list when an existing movie had no genres.
$campaign = get_posts( array( 'post_type' => 'sat_campaign', 'post_status' => 'any', 'numberposts' => 1,
    'meta_key' => '_sat_star_wars_50_demo', 'meta_value' => '1', 'fields' => 'ids',
) );
$target = $campaign ? ( get_post_meta( $campaign[0], '_sat_campaign', true )['targets'][0] ?? 0 ) : 0;
$movie = $target ? $store->by_post( (int) $target ) : null;
if ( $movie && 'msc_movie' === $movie['type'] && empty( $movie['data']['genres'] ) && 'tt0076759' === ( $movie['data']['external_ids']['imdb'] ?? '' ) ) {
    $resolved = $store->resolved( $movie['uuid'] );
    if ( array( 'Action', 'Adventure', 'Science Fiction' ) === ( $resolved['overrides']['genres'] ?? null ) ) {
        $store->local( $movie['uuid'], array(), array( 'genres' ), (int) $resolved['mapping']['local_version'] );
        WP_CLI::log( 'Restored the inherited genres for Star Wars: A New Hope.' );
    }
}
WP_CLI::success( 'Demo genre check complete.' );
