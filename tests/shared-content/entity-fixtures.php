<?php
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local network required.' ); }
wp_set_current_user( 1 ); require __DIR__ . '/network-profiles.php';
$sites = array(); foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { $key = \MSC\NetworkSites::key( $site->path ); if ( $key ) { $sites[ $key ] = (int) $site->blog_id; } }
function ec_at( int $site, callable $fn ) { switch_to_blog( $site ); try { return $fn(); } finally { restore_current_blog(); } }
function ec_publish( string $uuid, int $site, string $status = 'publish' ): array {
    return ec_at( $site, static function () use ( $uuid, $site, $status ) { $s = \MSC\Plugin::store(); $r = $s->get( $uuid ); \MSC\Plugin::materializer()->sync( (int) $r['id'], $site ); $m = $s->mapping( (int) $r['id'] ); $r = $s->local( $uuid, array(), array(), (int) $m['local_version'], $status ); $r['url'] = get_permalink( (int) $r['mapping']['post_id'] ); return $r; } );
}
function ec_create( int $site, string $type, array $values, string $status = 'publish' ): array {
    $r = ec_at( $site, static fn() => \MSC\Plugin::store()->create( $type, $values ) ); return ec_publish( $r['uuid'], $site, $status );
}
$tag = substr( wp_generate_uuid4(), 0, 8 );
$studio = ec_create( $sites['a'], 'msc_studio', array( 'title' => 'Production Company ' . $tag, 'content' => '<p>Company profile fixture.</p>' ) );
$draft = ec_create( $sites['a'], 'msc_studio', array( 'title' => 'Unpublished Company ' . $tag ), 'draft' );
$distributor = ec_create( $sites['a'], 'msc_distributor', array( 'title' => 'Distributor ' . $tag ) );
$streaming = ec_create( $sites['a'], 'msc_streaming', array( 'title' => 'Streaming Service ' . $tag ) );
$network = ec_create( $sites['a'], 'msc_tv_network', array( 'title' => 'TV Network ' . $tag ) );
$person = ec_create( $sites['a'], 'msc_person', array( 'title' => 'Director ' . $tag ) );
$movie = ec_create( $sites['a'], 'msc_movie', array( 'title' => 'Linked Movie ' . $tag, 'content' => '<p>Linked title overview.</p>', 'production_companies' => $studio['data']['title'], 'production_company_records' => array( $studio['uuid'], $draft['uuid'] ), 'distributor' => $distributor['data']['title'], 'streaming_service' => $streaming['data']['title'], 'directors' => array( $person['uuid'] ) ) );
$empty_show = ec_create( $sites['a'], 'msc_show', array( 'title' => 'Empty Show ' . $tag, 'network' => $network['data']['title'] ) );
$show = ec_create( $sites['a'], 'msc_show', array( 'title' => 'Show With Episodes ' . $tag ) );
$draft_episode = ec_create( $sites['a'], 'msc_episode', array( 'title' => 'Draft Episode ' . $tag, 'parent_show' => $empty_show['uuid'], 'season_number' => 1, 'episode_number' => 1 ), 'draft' );
$episode = ec_create( $sites['a'], 'msc_episode', array( 'title' => 'Published Episode ' . $tag, 'parent_show' => $show['uuid'], 'season_number' => 1, 'episode_number' => 1 ) );
$developer = ec_create( $sites['c'], 'msc_developer', array( 'title' => 'Game Developer ' . $tag ) );
$publisher = ec_create( $sites['c'], 'msc_publisher', array( 'title' => 'Game Publisher ' . $tag ) );
$console = ec_create( $sites['c'], 'msc_console', array( 'title' => 'PlayStation 5', 'platform_id' => 167, 'platform_kind' => 'Home console', 'release_date' => '2098-01-31' ) );
$pc = ec_create( $sites['c'], 'msc_console', array( 'title' => 'PC Platform ' . $tag, 'platform_id' => 6, 'platform_kind' => 'Computer' ) );
$game = ec_create( $sites['c'], 'msc_game', array( 'title' => 'Linked Game ' . $tag, 'release_date' => '2098-01-31', 'developers' => array( $developer['data']['title'] ), 'publishers' => array( $publisher['data']['title'] ), 'console_records' => array( $console['uuid'], $pc['uuid'] ) ) );
foreach ( array( $developer, $publisher, $console, $game ) as $r ) { ec_publish( $r['uuid'], $sites['d'] ); }
ec_publish( $studio['uuid'], $sites['b'] ); ec_publish( $movie['uuid'], $sites['b'] );
$dates = array(); foreach ( array( '2098-01-31', '2098-01-31', '2098-01-31', '2098-01-31', '2098-01-31', '2098-01-31', '2098-02-01', '2098-02-02', '2098-02-03' ) as $i => $date ) { $dates[] = ec_create( $sites['a'], 'msc_movie', array( 'title' => 'Busy Day ' . $tag . ' ' . ( $i + 1 ), 'release_date' => $date ) ); }
update_blog_option( $sites['a'], 'start_of_week', 1 );
$fixture = compact( 'sites', 'studio', 'draft', 'distributor', 'streaming', 'network', 'person', 'movie', 'empty_show', 'show', 'draft_episode', 'episode', 'developer', 'publisher', 'console', 'pc', 'game', 'dates' );
if ( ! defined( 'MSC_ENTITY_FIXTURE_INCLUDE' ) ) { echo wp_json_encode( $fixture ); }
