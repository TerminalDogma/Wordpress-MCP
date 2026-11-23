<?php
/** Apply the Iron Man editorial example locally, through ordinary site overrides. */
use MSC\{Database, Plugin, Presentation, Reviews};

if ( 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_network_options' ) ) {
    throw new RuntimeException( 'Run this example as a network administrator on a local installation.' );
}
$iron_man = get_page_by_path( 'iron-man', OBJECT, 'msc_movie' );
$store = Plugin::store();
$record = $iron_man ? $store->by_post( $iron_man->ID ) : null;
if ( ! $record ) { throw new RuntimeException( 'The Iron Man catalog entry is missing. Install the timeline example first.' ); }
$sample = json_decode( file_get_contents( __DIR__ . '/iron-man-editorial.json' ), true, 32, JSON_THROW_ON_ERROR );

Database::lock( 'iron-man-editorial-' . get_current_blog_id(), static function () use ( $record, $sample, $store, $args ): void {
    $values = $sample['film'];
    $people = array();
    $local_site = get_current_blog_id();
    $lookup = static function ( string $name, string $type ) use ( $store, $local_site ): string {
        foreach ( get_posts( array( 'post_type' => $type, 'title' => $name, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'numberposts' => 5 ) ) as $post ) {
            $existing = $store->by_post( $post->ID );
            if ( $existing && 'active' === $existing['state'] ) { return $existing['uuid']; }
        }
        $new = $store->create( $type, array( 'title' => $name ) );
        Plugin::materializer()->sync( (int) $new['id'], $local_site );
        return $new['uuid'];
    };
    foreach ( array_unique( array_merge( array_keys( $sample['cast'] ), ...array_values( $sample['crew'] ) ) ) as $name ) {
        $people[ $name ] = $lookup( $name, 'msc_person' );
    }
    $values['actors'] = array_values( array_intersect_key( $people, $sample['cast'] ) );
    $cast_roles = array();
    foreach ( $sample['cast'] as $name => $role ) { $cast_roles[] = $name . ' | ' . $role; }
    $values['cast_roles'] = implode( "\n", $cast_roles );
    foreach ( $sample['crew'] as $field => $names ) {
        $values[ $field ] = array_map( static fn( $name ) => $people[ $name ], $names );
    }
    foreach ( array( 'distributor_records' => array( 'msc_distributor', 'Paramount Pictures' ), 'streaming_service_records' => array( 'msc_streaming', 'Disney+' ), 'production_company_records' => array( 'msc_studio', 'Marvel Studios' ) ) as $field => list( $type, $name ) ) {
        if ( array_key_exists( $field, \MSC\Schema::get( 'msc_movie' )['fields'] ) ) { $values[ $field ] = array( $lookup( $name, $type ) ); }
    }
    foreach ( array( 'featured_image', 'poster', 'promotional_images' ) as $index => $field ) {
        $attachment = isset( $args[ $index ] ) ? (int) $args[ $index ] : 0;
        if ( $attachment && wp_attachment_is_image( $attachment ) ) {
            $reference = array( 'site_id' => $local_site, 'attachment_id' => $attachment );
            $values[ $field ] = 'promotional_images' === $field ? array( $reference ) : $reference;
        }
    }
    $mapping = $store->mapping( (int) $record['id'] );
    $store->local( $record['uuid'], $values, array(), (int) $mapping['local_version'] );
    $current = Presentation::record( $record['uuid'] );
    if ( $current && ! Reviews::own( $current ) && Reviews::enabled() ) {
        $review = wp_insert_post( wp_slash( array(
            'post_type' => 'msc_review', 'post_status' => 'publish',
            'post_title' => 'Iron Man review (editorial preview)',
            'post_content' => '<p>This local sample demonstrates a review score and verdict.</p><p>Jon Favreau keeps the action grounded in Tony Stark’s choices. The workshop sequences make each new piece of armor feel like an experiment, and Robert Downey Jr. keeps the quiet scenes moving as well as the fights.</p><p><a href="https://www.comingsoon.net/movies/reviews/44535-iron-man-2">Read Scott Chitwood’s original ComingSoon review from May 2, 2008</a>.</p>',
            'meta_input' => array( '_msc_review_item' => $record['uuid'], '_msc_review_score' => '9', '_msc_review_verdict' => '“Iron Man” is everything you’d hope a comic book movie would be. — Scott Chitwood, 2008', '_msc_review_archive_url' => 'https://www.comingsoon.net/movies/reviews/44535-iron-man-2', '_msc_movie_preview' => '1' ),
        ) ), true );
        if ( is_wp_error( $review ) ) { throw new RuntimeException( $review->get_error_message() ); }
    }
    $articles = array(
        array( 'Features', 'Iron Man and the first chapter of the MCU', 'The film lays groundwork for a shared world without taking its attention away from Tony Stark’s immediate choices.' ),
        array( 'Interviews', 'What an Iron Man production interview could cover', 'This interview outline focuses on building practical workshop props and planning the transition between physical armor and digital shots.' ),
        array( 'News', 'Iron Man viewing guide adds release and story order', 'The guide puts the film’s original release beside its place in the MCU story and links readers to the next film in both orders.' ),
    );
    foreach ( $articles as $index => list( $kind, $title, $summary ) ) {
        $key = 'iron-man-' . strtolower( $kind );
        if ( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft', 'private', 'trash' ), 'meta_key' => '_msc_movie_preview', 'meta_value' => $key, 'numberposts' => 1 ) ) ) { continue; }
        $id = wp_insert_post( wp_slash( array(
            'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title,
            'post_date' => current_datetime()->modify( '-' . $index . ' days' )->format( 'Y-m-d H:i:s' ),
            'post_content' => '<p><em>Sample editorial for the network presentation.</em></p><p>' . esc_html( $summary ) . '</p>',
            'meta_input' => array( '_msc_movie_preview' => $key, '_msc_demo_art' => $index ),
        ) ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $tags = wp_set_post_tags( $id, array( 'Iron Man', 'Marvel Cinematic Universe', $kind ) );
        if ( is_wp_error( $tags ) ) { throw new RuntimeException( $tags->get_error_message() ); }
    }
} );
WP_CLI::success( 'Iron Man editorial preview ' . get_permalink( $iron_man ) );
