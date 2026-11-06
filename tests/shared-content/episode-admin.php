<?php
/** Show-aware admin queries, site overrides, permissions and native title preservation. */
use MSC\{EpisodeAdmin,Plugin,Queue,Admin,Schema,Materializer};
if ( ! defined( 'WP_CLI' ) || ! is_multisite() || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Use a disposable local multisite.' ); }
require_once ABSPATH . 'wp-admin/includes/admin.php';
$GLOBALS['msc_episode_admin_checks'] = 0;
function ep_admin_check( $value, string $message ): void { ++$GLOBALS['msc_episode_admin_checks']; if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); } WP_CLI::log( 'PASS: ' . $message ); }
function ep_admin_drain(): void { for ( $i = 0; $i < 100 && Queue::status()['pending']; ++$i ) { $r = Queue::run( 1000 ); if ( $r['errors'] || ! $r['processed'] ) { throw new RuntimeException( 'Episode admin fixture synchronization failed.' ); } } }
function ep_admin_query( array $get, array $ids, array $extra = array() ): WP_Query {
    $saved = array( $_GET, $GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['current_screen'] ?? null );
    try {
        $_GET = $get; set_current_screen( 'edit-msc_episode' ); $q = new WP_Query(); $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $q;
        $q->query( array_replace( array( 'post_type' => 'msc_episode', 'post_status' => 'any', 'post__in' => $ids, 'posts_per_page' => 100, 'fields' => 'ids' ), $extra ) );
        if ( $GLOBALS['wpdb']->last_error ) { throw new RuntimeException( $GLOBALS['wpdb']->last_error ); } return $q;
    } finally { [ $_GET, $GLOBALS['wp_query'], $GLOBALS['wp_the_query'], $GLOBALS['current_screen'] ] = $saved; }
}
function ep_admin_selection( string $parent = '', string $season = '' ): array { $old = $_GET; try { $_GET = array( 'msc_parent_show' => $parent, 'msc_season' => $season ); return EpisodeAdmin::selection(); } finally { $_GET = $old; } }
wp_set_current_user( 1 ); $tag = substr( wp_generate_uuid4(), 0, 8 ); $sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( in_array( $site->path, array( '/site-a/', '/site-b/', '/site-c/' ), true ) ) { $sites[] = (int) $site->blog_id; } }
[ $a, $b, $c ] = $sites; switch_to_blog( $a );
try {
    $show = Plugin::store()->create( 'msc_show', array( 'title' => 'Episode Admin ' . $tag . ' A' ) ); $other = Plugin::store()->create( 'msc_show', array( 'title' => 'Episode Admin ' . $tag . ' Z' ) );
    $episodes = array();
    foreach ( array( 'ten' => array( 1, 10 ), 'two' => array( 1, 2 ), 'zero' => array( 0, 0 ), 'unknown' => array( null, null ), 'other' => array( 2, 1 ), 'unassigned' => array( null, null ), 'missing' => array( null, null ) ) as $key => $numbers ) {
        $data = array( 'title' => 'Episode ' . $tag . ' ' . $key );
        if ( ! in_array( $key, array( 'unassigned', 'missing' ), true ) ) { $data['parent_show'] = 'other' === $key ? $other['uuid'] : $show['uuid']; }
        if ( null !== $numbers[0] ) { $data['season_number'] = $numbers[0]; $data['episode_number'] = $numbers[1]; }
        $episodes[ $key ] = Plugin::store()->create( 'msc_episode', $data );
    }
    ep_admin_drain(); $posts = array(); foreach ( $episodes as $key => $record ) { $posts[ $key ] = msc_local_post_id( $record['uuid'] ); }
    Materializer::$writing = true; try { delete_post_meta( $posts['missing'], 'msc_parent_show' ); delete_post_meta( $posts['missing'], 'msc_season_number' ); } finally { Materializer::$writing = false; }
    $ids = array_values( $posts );
    $q = ep_admin_query( array(), $ids );
    ep_admin_check( array_slice( $q->posts, 0, 5 ) === array( $posts['zero'], $posts['two'], $posts['ten'], $posts['unknown'], $posts['other'] ), 'Native Episodes group by TV Show, numeric season and episode, with missing numbers last' );
    ep_admin_check( 7 === (int) $q->found_posts && 7 === count( array_unique( $q->posts ) ), 'Admin ordering retains drafts, accurate totals and unique native posts' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'], 'msc_season' => '1' ), $ids );
    ep_admin_check( $q->posts === array( $posts['two'], $posts['ten'] ), 'Show and season filters compose without turning episode 10 into episode 1 ordering' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'], 'msc_season' => '0' ), $ids ); ep_admin_check( $q->posts === array( $posts['zero'] ), 'Season zero remains a valid explicit filter' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'], 'msc_season' => 'unknown' ), $ids ); ep_admin_check( $q->posts === array( $posts['unknown'] ), 'Unset seasons do not become season zero' );
    $q = ep_admin_query( array( 'msc_parent_show' => 'unassigned' ), $ids ); $actual = $q->posts; sort( $actual ); $expected = array( $posts['unassigned'], $posts['missing'] ); sort( $expected ); ep_admin_check( $actual === $expected, 'Unassigned Episodes include both empty and absent parent metadata' );
    ep_admin_check( ! ep_admin_query( array( 'msc_parent_show' => "bad' OR 1=1" ), $ids )->posts, 'Invalid parent identifiers return no results' );
    ep_admin_check( ! ep_admin_query( array( 'msc_season' => array( '0' ) ), $ids )->posts, 'Malformed array-valued filters return no results' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'] ), $ids, array( 'orderby' => 'msc_season_number', 'order' => 'ASC' ) ); ep_admin_check( $q->posts[0] === $posts['zero'], 'Sorting the Season column keeps zero before positive seasons' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'], 'msc_season' => '1' ), $ids, array( 'orderby' => 'msc_episode_number', 'order' => 'DESC' ) ); ep_admin_check( $q->posts === array( $posts['ten'], $posts['two'] ), 'Sortable Episode columns compare numbers rather than text' );
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'] ), $ids, array( 's' => 'ten' ) ); ep_admin_check( $q->posts === array( $posts['ten'] ), 'WordPress search composes with the Show filter' );
    $q = ep_admin_query( array(), $ids, array( 'orderby' => 'ID', 'order' => 'DESC' ) ); $expected = $ids; rsort( $expected ); ep_admin_check( $q->posts === $expected, 'Explicit existing WordPress sort choices remain available' );
    $saved_screen = $GLOBALS['current_screen'] ?? null; set_current_screen( 'edit-msc_episode' ); $title = get_the_title( $posts['ten'] );
    ep_admin_check( str_contains( $title, $show['data']['title'] . ' — S01E10 — ' ), 'Episode row titles include the Show and SxxExx context' );
    ep_admin_check( get_post( $posts['ten'] )->post_title === $episodes['ten']['data']['title'] && msc_get_global( $episodes['ten']['uuid'], 'title' ) === $episodes['ten']['data']['title'], 'Admin title context never rewrites stored native or shared titles' );
    set_current_screen( 'post' ); ep_admin_check( get_the_title( $posts['ten'] ) === $episodes['ten']['data']['title'], 'Editors and other screens retain the actual Episode title' ); $GLOBALS['current_screen'] = $saved_screen;
    $columns = EpisodeAdmin::columns( array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) ); ep_admin_check( array_slice( array_keys( $columns ), 2, 4 ) === array( 'msc_parent_show', 'msc_season_number', 'msc_episode_number', 'msc_air_date' ), 'Native lists expose parent, season, episode number and air date columns' );
    $actions = EpisodeAdmin::row_actions( array(), get_post( msc_local_post_id( $show['uuid'] ) ) ); ep_admin_check( isset( $actions['msc_episodes'], $actions['msc_add_episode'] ) && str_contains( $actions['msc_add_episode'], $show['uuid'] ), 'Show row actions browse Episodes and preselect the parent for creation' );
    $old_get = $_GET; $old_screen = $GLOBALS['current_screen'] ?? null; $_GET = array( 'msc_parent_show' => $show['uuid'] ); set_current_screen( 'edit-msc_episode' );
    ep_admin_check( str_contains( admin_url( 'post-new.php?post_type=msc_episode' ), 'parent=' . $show['uuid'] ), 'Add New from a Show-filtered native list keeps the selected parent' );
    ep_admin_check( ! str_contains( get_admin_url( $b, 'post-new.php?post_type=msc_episode' ), 'parent=' ), 'Scoped Add New does not rewrite another site’s admin URLs' );
    $secondary = new WP_Query( array( 'post_type' => 'msc_episode', 'post_status' => 'any', 'post__in' => $ids, 'posts_per_page' => 100, 'fields' => 'ids' ) );
    ep_admin_check( count( $secondary->posts ) === count( $ids ) && ! $secondary->get( '_msc_episode_sort' ), 'Parent filters and custom sorting do not affect secondary queries' );
    $_GET = $old_get; $GLOBALS['current_screen'] = $old_screen;
    $global = EpisodeAdmin::shared_rows( ep_admin_selection( $show['uuid'] ) ); ep_admin_check( array_column( $global['records'], 'uuid' ) === array( $episodes['zero']['uuid'], $episodes['two']['uuid'], $episodes['ten']['uuid'], $episodes['unknown']['uuid'] ), 'Global Episode lists include drafts in Show/season/episode order' );
    ep_admin_check( EpisodeAdmin::shared_rows( ep_admin_selection( $show['uuid'], '0' ) )['total'] === 1, 'Global filters preserve season zero' );
    ep_admin_check( EpisodeAdmin::shared_rows( ep_admin_selection(), $tag . ' A' )['total'] === 4, 'Global search can find Episodes by parent Show title' );
    // A site-specific move changes native navigation, while the authoritative global catalog stays intact.
    switch_to_blog( $b );
    $r = Plugin::store()->resolved( $episodes['ten']['uuid'] ); Plugin::store()->local( $r['uuid'], array( 'parent_show' => $other['uuid'], 'season_number' => 3, 'episode_number' => 4 ), array(), (int) $r['mapping']['local_version'] );
    $local_ids = array(); foreach ( $episodes as $key => $record ) { $local_ids[ $key ] = msc_local_post_id( $record['uuid'] ); }
    $q = ep_admin_query( array( 'msc_parent_show' => $show['uuid'] ), array_values( $local_ids ) ); ep_admin_check( ! in_array( $local_ids['ten'], $q->posts, true ), 'Native Show filters honor the current site’s parent override' );
    $q = ep_admin_query( array( 'msc_parent_show' => $other['uuid'], 'msc_season' => '3', 'msc_mode' => 'override' ), array_values( $local_ids ) ); ep_admin_check( $q->posts === array( $local_ids['ten'] ), 'Parent, season and inheritance filters work together' );
    $screen = $GLOBALS['current_screen'] ?? null; set_current_screen( 'edit-msc_episode' ); ep_admin_check( str_contains( get_the_title( $local_ids['ten'] ), $other['data']['title'] . ' — S03E04' ), 'Native row labels use effective local relationships and numbering' ); $GLOBALS['current_screen'] = $screen;
    ep_admin_check( EpisodeAdmin::shared_rows( ep_admin_selection( $show['uuid'] ) )['total'] === 4, 'The global catalog preserves global parentage after a local move' ); restore_current_blog();
    $paged_show = Plugin::store()->create( 'msc_show', array( 'title' => 'Episode Admin ' . $tag . ' Pagination' ) );
    for ( $n = 1; $n <= 51; ++$n ) { Plugin::store()->create( 'msc_episode', array( 'title' => 'Paged Episode ' . $tag . ' ' . $n, 'parent_show' => $paged_show['uuid'], 'season_number' => 1, 'episode_number' => $n ) ); }
    $first = EpisodeAdmin::shared_rows( ep_admin_selection( $paged_show['uuid'] ) ); $second = EpisodeAdmin::shared_rows( ep_admin_selection( $paged_show['uuid'] ), '', 2 );
    ep_admin_check( 51 === $first['total'] && 50 === count( $first['records'] ) && 1 === count( $second['records'] ) && 51 === $second['records'][0]['data']['episode_number'], 'Global pagination follows the Show/episode sort across the 50-record boundary' );
    $old_get = $_GET; $_GET = array( 'msc_parent_show' => $paged_show['uuid'], 'msc_season' => '1', 'msc_search' => $tag ); ob_start(); EpisodeAdmin::shared_list(); $html = ob_get_clean(); $_GET = $old_get;
    ep_admin_check( str_contains( $html, 'msc_paged=2' ) && str_contains( $html, 'msc_season=1' ) && str_contains( $html, 'msc_search=' . $tag ) && str_contains( $html, $paged_show['uuid'] ), 'Global pagination retains Show, season and search filters' );
    $uid = wp_insert_user( array( 'user_login' => 'episode-admin-editor-' . $tag, 'user_pass' => wp_generate_password(), 'role' => 'editor' ) ); add_user_to_blog( $a, (int) $uid, 'editor' ); wp_set_current_user( (int) $uid );
    $actions = EpisodeAdmin::row_actions( array(), get_post( msc_local_post_id( $show['uuid'] ) ) ); ep_admin_check( isset( $actions['msc_episodes'] ) && ! isset( $actions['msc_add_episode'] ), 'Local-only editors can browse Episodes without receiving global creation actions' );
    ob_start(); EpisodeAdmin::context( Plugin::store()->resolved( $episodes['ten']['uuid'] ) ); $html = ob_get_clean(); ep_admin_check( str_contains( $html, 'Manage TV Show' ) && str_contains( $html, 'S01E10' ), 'Episode editors expose their parent and sibling navigation' );
    wp_set_current_user( 1 ); ep_admin_drain();
    WP_CLI::success( $GLOBALS['msc_episode_admin_checks'] . ' Episode administration, filtering, ordering, scope and permission checks passed.' );
} finally { wp_set_current_user( 1 ); restore_current_blog(); }
