<?php
use MSC\{CastRoles, NetworkSites, PersonIdentity, Plugin, Presentation, Schema, TimelineDemo, Timelines};

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
        array( 'title' => 'Prequel', 'kind' => 'show', 'section' => 'Early years', 'date' => 'Centuries before the war', 'precision' => 'relative', 'era' => 'era-one' ),
        array( 'title' => 'Main story', 'kind' => 'movie', 'date' => '2220-03-14', 'precision' => 'exact', 'era' => 'era-one' ),
    ),
);
$checked = Schema::values( Timelines::TYPE, array( 'chronology' => $model ) )['chronology'];
$check( array( 'Prequel', 'Main story' ) === array_column( $checked['entries'], 'title' ), 'Editorial order is independent of date formats' );
$check( 'TV Shows' === $checked['columns'][1]['label'] && 'Early years' === $checked['sections'][0]['label'] && $checked['sections'][0]['id'] === $checked['entries'][0]['section'], 'Older headings and movie/TV lanes become named section and column choices' );
$franchise = $store->get( $seed['data']['franchise'] ?? '' );
$check( $franchise && 'msc_franchise' === $franchise['type'] && ! empty( $franchise['data']['movies'] ) && ! empty( $franchise['data']['shows'] ), 'The MCU example links a populated franchise' );
$invalid = $model; $invalid['eras'][0]['color'] = '#112233; display:none';
$reject( $invalid, 'Era colors cannot include arbitrary CSS' );
$invalid = $model; $invalid['entries'][0]['era'] = 'unknown';
$reject( $invalid, 'Entries cannot reference a missing era' );
$invalid = $model; $invalid['entries'][0]['date'] = '';
$reject( $invalid, 'Missing date labels are rejected unless explicitly undated' );
$invalid = $model; $invalid['entries'][0]['item'] = $seed['uuid'];
$reject( $invalid, 'A catalog link cannot target the timeline itself' );
$invalid = $model; $invalid['entries'][0]['image'] = array( 'url' => 'https://example.com/image.jpg' );
$reject( $invalid, 'Artwork belongs to the WordPress media library' );

switch_to_blog( $sites['a'] );
try {
    require_once '/msc-examples/demo-art.php';
    $tag = substr( wp_generate_uuid4(), 0, 8 );
    $name = 'Timeline artist ' . $tag;
    $person = $store->create( 'msc_person', array( 'title' => $name, 'headshot' => msc_demo_art( 'actor' ) ) );
    $movie = $store->create( 'msc_movie', array( 'title' => 'Timeline screen credit ' . $tag, 'actors' => array( $person['uuid'] ), 'cast_roles' => $name . ' | Pilot' ) );
    $model = array(
        'columns' => array( array( 'id' => 'team', 'label' => 'Crew' ), array( 'id' => 'feature', 'label' => 'Features' ), array( 'id' => 'series', 'label' => 'Series' ) ),
        'sections' => array( array( 'id' => 'launch', 'label' => 'Before the launch' ) ),
        'entries' => array( array( 'title' => $name, 'column' => 'team', 'section' => 'launch', 'item' => $person['uuid'], 'precision' => 'undated' ) ),
    );
    $timeline = $store->create( Timelines::TYPE, array( 'title' => 'Custom timeline ' . $tag, 'chronology' => $model ) );
    $publish = static function ( array $record ) use ( $store, $sites ): int {
        $id = (int) $record['id'];
        Plugin::materializer()->sync( $id, $sites['a'] );
        $map = $store->mapping( $id );
        $store->local( $id, array(), array(), (int) $map['local_version'], 'publish' );
        Plugin::materializer()->sync( $id, $sites['a'] );
        return (int) $map['post_id'];
    };
    $person_post = $publish( $person );
    $movie_post = $publish( $movie );
    $check( ! Timelines::related( Presentation::record( $person['id'] ) ), 'Draft timelines are absent from entry backlinks' );
    $timeline_post = $publish( $timeline );
    $linked = Presentation::record( $person['id'] );
    $related = Timelines::related( $linked );
    $check( $timeline['uuid'] === $related[0]['uuid'] && array( 0 ) === $related[0]['timeline_matches'], 'Published timelines link back from any linked catalog type and identify the entry' );
    $art = Presentation::artwork( $linked, 'medium' );
    $check( ! empty( $art['url'] ) && $person['data']['headshot'] === $art['reference'], 'Timeline artwork uses the linked record’s native artwork field' );
    $map = $store->mapping( (int) $timeline['id'] );
    switch_to_blog( $sites['b'] );
    try { $check( ! Timelines::related( $person ), 'Publication on Site A does not expose a timeline link on Site B' ); }
    finally { restore_current_blog(); }
    $check( 'Pilot' === CastRoles::by_actor( $movie['data']['cast_roles'] )[ PersonIdentity::name( $name ) ], 'Character credits are connected to the cast member by normalized name' );
    echo 'TIMELINE_FLEX_JSON:' . wp_json_encode( array( 'timeline' => get_permalink( $timeline_post ), 'person' => get_permalink( $person_post ), 'movie' => get_permalink( $movie_post ), 'name' => $name, 'art' => $art['url'] ) ) . "\n";
} finally { restore_current_blog(); }
WP_CLI::success( 'Timeline validation, publication and seeding checks passed.' );
