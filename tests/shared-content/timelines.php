<?php
use MSC\{NetworkSites, Plugin, Presentation, Schema, TimelineDemo, Timelines};

if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() || '1' !== getenv( 'MSC_DISPOSABLE_TESTS' ) ) {
    throw new RuntimeException( 'Use a disposable local network.' );
}

wp_set_current_user( 1 );
$store = Plugin::store();
$sites = array();
foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0 ) ) as $site ) {
    if ( $name = NetworkSites::key( $site->path ) ) { $sites[ $name ] = (int) $site->blog_id; }
}
$check = static function ( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    WP_CLI::log( 'PASS: ' . $message );
};
$reject = static function ( $model, $message ) use ( $check ) {
    try { Timelines::sanitize( $model ); }
    catch ( InvalidArgumentException $e ) { $check( true, $message ); return; }
    throw new RuntimeException( $message );
};

$check( Schema::enabled( Timelines::TYPE, $sites['a'] ) && Schema::enabled( Timelines::TYPE, $sites['b'] )
    && ! Schema::enabled( Timelines::TYPE, $sites['c'] ) && ! Schema::enabled( Timelines::TYPE, $sites['d'] ),
    'Only movie and TV sites have Timelines enabled by default' );

$seed = TimelineDemo::seed();
$rows = $seed['data']['chronology']['entries'];
$first_avenger = array_search( 'Captain America: The First Avenger', array_column( $rows, 'title' ), true );
$endgame = array_search( 'Avengers: Endgame', array_column( $rows, 'title' ), true );
$check( 56 === count( $rows ) && is_int( $first_avenger ) && is_int( $endgame ) && $first_avenger < $endgame && 'Five years after the Snap' === $rows[ $endgame ]['date'],
    'MCU example uses story chronology and retains relative dates' );
$check( $seed['uuid'] === TimelineDemo::seed()['uuid'] && $seed['version'] === TimelineDemo::seed()['version'],
    'Repeat setup retains the existing example and its edits' );
foreach ( array( 'a', 'b' ) as $key ) {
    switch_to_blog( $sites[ $key ] );
    try { $check( (bool) Presentation::record( $seed['id'] ), 'Published MCU example is visible on Site ' . strtoupper( $key ) ); }
    finally { restore_current_blog(); }
}

$model = array(
    'eras' => array( array( 'id' => 'era-one', 'label' => 'Era One', 'color' => '#113399' ) ),
    'entries' => array(
        array( 'title' => 'Prequel', 'kind' => 'show', 'date' => 'Centuries before the war', 'precision' => 'relative', 'era' => 'era-one' ),
        array( 'title' => 'Main story', 'kind' => 'movie', 'date' => '2220-03-14', 'precision' => 'exact', 'era' => 'era-one' ),
    ),
);
$checked = Schema::values( Timelines::TYPE, array( 'chronology' => $model ) )['chronology'];
$check( array( 'Prequel', 'Main story' ) === array_column( $checked['entries'], 'title' ), 'Editorial order is independent of date formats' );
$invalid = $model; $invalid['eras'][0]['color'] = '#112233; display:none';
$reject( $invalid, 'Era colors cannot include arbitrary CSS' );
$invalid = $model; $invalid['entries'][0]['era'] = 'unknown';
$reject( $invalid, 'Entries cannot reference a missing era' );
$invalid = $model; $invalid['entries'][0]['date'] = '';
$reject( $invalid, 'Missing date labels are rejected unless explicitly undated' );
$invalid = $model; $invalid['entries'][0]['item'] = $seed['uuid'];
$reject( $invalid, 'Links must reference the correct catalog type' );
$invalid = $model; $invalid['entries'][0]['image'] = array( 'url' => 'https://example.com/image.jpg' );
$reject( $invalid, 'Artwork belongs to the WordPress media library' );

switch_to_blog( $sites['a'] );
try {
    $timeline = $store->create( Timelines::TYPE, array( 'title' => 'Timeline draft fixture ' . wp_generate_uuid4(), 'chronology' => $model ) );
    Plugin::materializer()->sync( (int) $timeline['id'], $sites['a'] );
    $check( ! Presentation::record( $timeline['id'] ), 'New timelines require publication before public viewing' );
} finally { restore_current_blog(); }
WP_CLI::success( 'Timeline validation, publication and seeding checks passed.' );
