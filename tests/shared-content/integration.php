<?php
/** Real WordPress Multisite integration suite. Run only in a disposable development network. */
use MSC\{Plugin,Schema,Database,Queue,Media,Seo};
if ( ! is_multisite() || ! defined( 'WP_CLI' ) ) { throw new RuntimeException( 'Requires a disposable Multisite WP-CLI environment.' ); }
$GLOBALS['msc_checks'] = 0;
$batch_size = max( 205, min( 5000, (int) ( $args[0] ?? 205 ) ) );
function check_msc( $condition, string $message ): void { ++$GLOBALS['msc_checks']; if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } WP_CLI::log( 'PASS: ' . $message ); }
function reject_msc( callable $fn, string $message ): void { $caught = false; try { $fn(); } catch ( Throwable $e ) { $caught = true; } check_msc( $caught, $message ); }
function drain_msc(): void { for ( $i = 0; $i < 100; ++$i ) { $s = Queue::status(); if ( ! $s['pending'] ) { check_msc( 0 === $s['failed'], 'Queue drained without failed jobs' ); return; } $r = Queue::run( 1000 ); if ( ! $r['processed'] || $r['errors'] ) { throw new RuntimeException( 'Queue failed: ' . wp_json_encode( $r ) ); } } throw new RuntimeException( 'Queue did not drain.' ); }
function at_msc( int $site, callable $fn ) { switch_to_blog( $site ); try { return $fn(); } finally { restore_current_blog(); } }
function local_msc( int $site, $id, array $values = array(), array $reset = array(), ?string $status = null ): array { return at_msc( $site, static function () use ( $id, $values, $reset, $status ) { $m = msc_get_record( $id )['mapping']; return Plugin::store()->local( $id, $values, $reset, (int) $m['local_version'], $status ); } ); }
function request_msc( string $method, string $path, array $data = array() ) { $q = new WP_REST_Request( $method, $path ); foreach ( $data as $k => $v ) { $q->set_param( $k, $v ); } return rest_do_request( $q ); }
wp_set_current_user( 1 );
$sites = array(); foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( in_array( $site->path, array( '/site-a/', '/site-b/', '/site-c/' ), true ) ) { $sites[] = (int) $site->blog_id; } }
check_msc( count( $sites ) === 3, 'Three independent subsites available' );
[$a,$b,$c] = $sites; $s = Plugin::store();
Database::migrate(); Database::migrate(); check_msc( 1 === (int) get_network_option( get_current_network_id(), 'msc_db_version' ), 'Repeatable database migrations' );
foreach ( $sites as $site ) { Schema::participate( $site, array_keys( Schema::all() ) ); }
drain_msc();
at_msc( $a, static function () { Plugin::register_posts(); check_msc( post_type_exists( 'msc_show' ) && post_type_exists( 'msc_episode' ), 'Both native CPTs registered' ); } );
$tag = substr( wp_generate_uuid4(), 0, 8 );
$schemas = Schema::all(); $schemas['msc_show']['fields']['test_boolean'] = array( 'type' => 'boolean' ); $schemas['msc_show']['fields']['test_locked'] = array( 'type' => 'text', 'override' => false ); Schema::save( $schemas );
$r = at_msc( $a, static fn() => Plugin::store()->create( 'msc_show', array( 'title' => 'Integration Show ' . $tag, 'premiere_date' => '2027-01-01', 'content' => '<!-- wp:paragraph --><p>Global description</p><!-- /wp:paragraph -->', 'seo_title' => 'Global SEO', 'test_boolean' => true, 'test_locked' => 'Network only' ) ) );
$id = (int) $r['id']; $uuid = $r['uuid'];
check_msc( 36 === strlen( $uuid ), 'Permanent UUID created from Site A' );
check_msc( Queue::status()['pending'] > 0, 'Global save queues asynchronous propagation' );
drain_msc();
foreach ( $sites as $site ) { $m = $s->mapping( $id, $site ); check_msc( $m && $m['post_id'] && 1 === (int) $m['version'], 'Local post and mapping exist on site ' . $site ); }
$r = at_msc( $b, static fn() => Plugin::store()->update( $uuid, array( 'premiere_date' => '2027-02-01' ), 1 ) ); drain_msc();
foreach ( array( $a, $c ) as $site ) { check_msc( '2027-02-01' === msc_get_field( $id, 'premiere_date', $site ), 'Global update from Site B resolves on site ' . $site ); }
local_msc( $c, $id, array( 'premiere_date' => '2027-03-01', 'content' => 'Site C description', 'seo_title' => 'Site C SEO' ) );
$r = at_msc( $a, static fn() => Plugin::store()->update( $id, array( 'premiere_date' => '2027-04-01' ), 2 ) ); drain_msc();
foreach ( array( $a, $b ) as $site ) { check_msc( '2027-04-01' === msc_get_field( $id, 'premiere_date', $site ), 'Inherited date follows newest global value on site ' . $site ); }
check_msc( '2027-03-01' === msc_get_field( $id, 'premiere_date', $c ), 'Site C override survives global update' );
local_msc( $c, $id, array(), array( 'premiere_date' ) ); check_msc( '2027-04-01' === msc_get_field( $id, 'premiere_date', $c ) && ! msc_is_overridden( $id, 'premiere_date', $c ), 'Reset inherits latest global value' );
local_msc( $c, $id, array( 'title' => '', 'number_of_seasons' => 0, 'test_boolean' => false, 'genres' => array() ) );
foreach ( array( 'title' => '', 'number_of_seasons' => 0, 'test_boolean' => false, 'genres' => array() ) as $field => $expected ) { check_msc( $expected === msc_get_field( $id, $field, $c ) && msc_is_overridden( $id, $field, $c ), 'Explicit falsey override preserved: ' . $field ); }
local_msc( $c, $id, array(), array( 'title' ) );
reject_msc( static fn() => at_msc( $a, static fn() => Plugin::store()->update( $id, array( 'title' => 'Stale' ), 1 ) ), 'Stale global edit rejected' );
$m = $s->mapping( $id, $c ); local_msc( $c, $id, array( 'original_title' => 'Changed' ) );
reject_msc( static fn() => at_msc( $c, static fn() => Plugin::store()->local( $id, array( 'title' => 'Stale' ), array(), (int) $m['local_version'] ) ), 'Stale local edit rejected' );
reject_msc( static fn() => local_msc( $c, $id, array( 'test_locked' => 'Override' ) ), 'Network-locked field cannot be overridden' );
check_msc( 'Site C description' === msc_get_field( $id, 'content', $c ) && 'Site C SEO' === msc_get_field( $id, 'seo_title', $c ), 'Description and SEO are independently local' );
check_msc( str_contains( msc_get_field( $id, 'content', $a ), '<!-- wp:paragraph -->' ), 'Gutenberg block markup survives global sanitization' );
// Independent native presentation metadata is never overwritten by projection updates.
foreach ( array( $a => 'templates/card.php', $b => 'templates/schedule.php', $c => 'templates/compact.php' ) as $site => $template ) {
	at_msc( $site, static function () use ( $id, $template ) { update_post_meta( msc_local_post_id( $id ), '_wp_page_template', $template ); } );
	Plugin::materializer()->sync( $id, $site );
	check_msc( $template === at_msc( $site, static fn() => get_post_meta( msc_local_post_id( $id ), '_wp_page_template', true ) ), 'Site-specific native template selection survives synchronization on site ' . $site );
}
at_msc( $a, static function () use ( $id ) {
	$post = msc_local_post_id( $id ); $before = get_post_meta( $post, 'msc_test_locked', true );
	check_msc( false === update_post_meta( $post, 'msc_test_locked', 'Bypass' ) && $before === get_post_meta( $post, 'msc_test_locked', true ), 'Direct materialized metadata writes cannot bypass field policies' );
	check_msc( false === update_post_meta( $post, '_msc_uuid', wp_generate_uuid4() ), 'Reserved mapping metadata cannot be overwritten through generic meta APIs' );
} );
// Native WordPress editor update creates an override, never a global edit.
at_msc( $b, static function () use ( $id ) { $post = msc_local_post_id( $id ); wp_update_post( array( 'ID' => $post, 'post_title' => 'Native local title' ) ); check_msc( 'Native local title' === msc_get_field( $id, 'title' ), 'Native post edit creates local override' ); } );
check_msc( 'Integration Show ' . $tag === msc_get_global( $id, 'title' ), 'Native local editing does not change global title' );
// Indexed relationship and release-date access.
$episode = at_msc( $a, static fn() => Plugin::store()->create( 'msc_episode', array( 'title' => 'Pilot ' . $tag, 'parent_show' => $uuid, 'season_number' => 1, 'episode_number' => 1, 'release_date' => '2099-01-01', 'air_time' => '20:00', 'time_zone' => 'America/New_York' ) ) ); drain_msc();
foreach ( $sites as $site ) { local_msc( $site, $id, array(), array(), 'publish' ); local_msc( $site, $episode['id'], array(), array(), 'publish' ); }
drain_msc();
at_msc( $a, static function () use ( $uuid, $episode ) {
	check_msc( in_array( (int) $episode['id'], msc_episodes( $uuid ), true ), 'Episode relationship resolves through network UUID' );
	check_msc( msc_next_episode( $uuid )['uuid'] === $episode['uuid'], 'Next upcoming episode uses release-date index' );
	check_msc( isset( msc_seasons( $uuid )[1] ), 'Episodes grouped by season' );
	check_msc( msc_related( $episode['id'], 'parent_show' )['uuid'] === $uuid, 'Episode navigates to its parent show' );
} );
reject_msc( static fn() => at_msc( $a, static fn() => Plugin::store()->update( $episode['id'], array( 'parent_show' => $episode['uuid'] ), 1 ) ), 'Wrong relationship target type rejected' );
reject_msc( static fn() => Schema::sanitize( array( 'type' => 'date' ), '2027-02-30' ), 'Impossible calendar date rejected' );
reject_msc( static fn() => Schema::sanitize( array( 'type' => 'number' ), 'nonnumeric' ), 'Invalid numeric input rejected' );
check_msc( ! str_contains( Schema::sanitize( array( 'type' => 'richtext' ), '<script>alert(1)</script><p>Safe</p>' ), '<script' ), 'Unsafe rich HTML removed' );
// Owner-scoped media reference; same numeric ID on another site cannot be confused.
$attachment = at_msc( $a, static function () {
	$bits = wp_upload_bits( 'msc-test.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j9j8AAAAASUVORK5CYII=' ) );
	if ( $bits['error'] ) { throw new RuntimeException( $bits['error'] ); }
	return wp_insert_attachment( array( 'post_title' => 'MSC test image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $bits['file'] );
} );
$r = at_msc( $a, static fn() => Plugin::store()->update( $id, array( 'featured_image' => array( 'site_id' => $a, 'attachment_id' => $attachment ) ), (int) Plugin::store()->get( $id )['version'] ) ); drain_msc();
check_msc( str_contains( msc_media_url( (array) msc_get_field( $id, 'featured_image', $b ) ), 'msc-test' ), 'Shared image URL resolves at owning site' );
local_msc( $c, $id, array( 'featured_image' => array() ) ); check_msc( array() === msc_get_field( $id, 'featured_image', $c ), 'Local blank image override stays blank' );
check_msc( '' === Media::url( array( 'site_id' => $a, 'attachment_id' => 999999999 ) ), 'Unavailable media has safe empty fallback' );
$c_attachment = at_msc( $c, static function () {
	$bits = wp_upload_bits( 'msc-local-test.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j9j8AAAAASUVORK5CYII=' ) );
	return wp_insert_attachment( array( 'post_title' => 'Local MSC image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $bits['file'] );
} );
local_msc( $c, $id, array( 'featured_image' => array( 'site_id' => $c, 'attachment_id' => $c_attachment ) ) );
check_msc( msc_get_field( $id, 'featured_image', $c )['site_id'] === $c && msc_media_url( msc_get_field( $id, 'featured_image', $c ) ) !== msc_media_url( msc_get_field( $id, 'featured_image', $a ) ), 'Different local featured image resolves with explicit ownership' );

// Real REST permission checks.
$server = rest_get_server(); do_action( 'rest_api_init', $server );
at_msc( $a, static function () use ( $uuid, $id ) {
	$response = request_msc( 'GET', '/msc/v1/records/' . $uuid ); check_msc( 200 === $response->get_status(), 'Authorized REST read succeeds' );
	$response = request_msc( 'PATCH', '/msc/v1/records/' . $uuid, array( 'version' => 1, 'data' => array( 'title' => 'Stale' ) ) ); check_msc( 409 === $response->get_status(), 'REST returns conflict for stale global version' );
	$m = Plugin::store()->mapping( $id );
	$response = request_msc( 'POST', '/wp/v2/msc_show/' . $m['post_id'], array( 'title' => 'REST native local', 'msc_expected_version' => (int) $m['local_version'] ) ); check_msc( 200 === $response->get_status(), 'Native Gutenberg REST save with version succeeds' );
	$response = request_msc( 'POST', '/wp/v2/msc_show/' . $m['post_id'], array( 'title' => 'Stale native', 'msc_expected_version' => (int) $m['local_version'] ) ); check_msc( 409 === $response->get_status(), 'Native Gutenberg stale REST save rejected' );
} );
$user = wp_insert_user( array( 'user_login' => 'msc-readonly-' . $tag, 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
add_user_to_blog( $a, $user, 'subscriber' ); wp_set_current_user( $user );
at_msc( $a, static function () use ( $uuid, $id ) {
	reject_msc( static fn() => Plugin::store()->update( $id, array( 'title' => 'Unauthorized' ), 3 ), 'Subscriber cannot change global information' );
	$response = request_msc( 'PATCH', '/msc/v1/records/' . $uuid . '/local', array( 'local_version' => 1, 'values' => array( 'title' => 'Unauthorized' ) ) ); check_msc( 403 === $response->get_status(), 'Subscriber REST local write denied' );
	$response = request_msc( 'GET', '/msc/v1/queue' ); check_msc( 403 === $response->get_status(), 'Subscriber cannot read network queue' );
} );
$editor = wp_insert_user( array( 'user_login' => 'msc-editor-' . $tag, 'user_pass' => wp_generate_password() ) );
add_user_to_blog( $a, $editor, 'editor' ); wp_set_current_user( $editor );
at_msc( $a, static function () use ( $id ) {
	reject_msc( static fn() => Plugin::store()->update( $id, array( 'title' => 'Unauthorized global' ), 1 ), 'Local editor has no implicit global editing authority' );
	$m = Plugin::store()->mapping( $id ); Plugin::store()->local( $id, array( 'original_title' => 'Authorized editor override' ), array(), (int) $m['local_version'] );
	check_msc( 'Authorized editor override' === msc_get_field( $id, 'original_title' ), 'Site A editor may override locally' );
} );
at_msc( $b, static function () use ( $id ) { reject_msc( static fn() => Plugin::store()->local( $id, array( 'title' => 'Cross-site attack' ), array(), 1 ), 'Site A editor has no automatic override authority on Site B' ); } );
$publisher = wp_insert_user( array( 'user_login' => 'msc-publisher-' . $tag, 'user_pass' => wp_generate_password() ) );
add_user_to_blog( $a, $publisher, 'subscriber' ); $publisher_user = new WP_User( $publisher, '', $a ); $publisher_user->add_cap( 'msc_publish_local' ); wp_set_current_user( $publisher );
at_msc( $a, static function () use ( $id ) {
	$m = Plugin::store()->mapping( $id ); Plugin::store()->local( $id, array(), array(), (int) $m['local_version'], 'draft' );
	check_msc( 'draft' === get_post_status( msc_local_post_id( $id ) ), 'Publish-only user changes publication without field-edit capability' );
	reject_msc( static fn() => Plugin::store()->local( $id, array( 'title' => 'Unauthorized' ), array(), (int) Plugin::store()->mapping( $id )['local_version'] ), 'Publish-only user cannot change field overrides' );
} );
wp_set_current_user( 1 ); local_msc( $a, $id, array(), array(), 'publish' );
wp_set_current_user( 0 );
at_msc( $a, static function () use ( $uuid ) { $response = request_msc( 'GET', '/msc/v1/records/' . $uuid ); check_msc( 200 === $response->get_status() && ! isset( $response->get_data()['global'] ), 'Public read reveals only published effective data' ); } );
wp_set_current_user( 1 );
// Real query flags, robots, sitemap exclusions, block rendering, and native WP_Query.
at_msc( $a, static function () use ( $id, $uuid ) {
	global $wp_query; $old = $wp_query; $wp_query = new WP_Query( array( 'post_type' => 'msc_show', 'p' => msc_local_post_id( $id ) ) );
	check_msc( $wp_query->have_posts(), 'Standard WP_Query retrieves materialized post' );
	check_msc( ! empty( apply_filters( 'wp_robots', array( 'index' => true ) )['noindex'] ), 'Singular noindex directive enforced' );
	check_msc( ! isset( apply_filters( 'wp_sitemaps_post_types', get_post_types( array( 'public' => true ), 'objects' ) )['msc_show'] ), 'Core XML sitemap excludes shared type' );
	check_msc( str_contains( render_block( array( 'blockName' => 'msc/record', 'attrs' => array( 'uuid' => $uuid ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ), 'msc-record' ), 'Dynamic Gutenberg block renders a published shared record' );
	$wp_query = new WP_Query( array( 'post_type' => 'msc_show' ) ); check_msc( Seo::enforced(), 'CPT archive is noindex' ); $wp_query = $old;
} );
at_msc( $a, static function () {
	register_taxonomy( 'msc_test_genre', 'msc_show', array( 'public' => true ) );
	check_msc( ! isset( apply_filters( 'wp_sitemaps_taxonomies', get_taxonomies( array( 'public' => true ), 'objects' ) )['msc_test_genre'] ), 'Core taxonomy sitemap excludes taxonomy attached to noindex type' );
} );
$invalid = Schema::all(); unset( $invalid['msc_show']['fields']['title'] ); reject_msc( static fn() => Schema::save( $invalid ), 'Removing required title schema is rejected' );
$invalid = Schema::all(); $invalid['msc_show']['fields']['premiere_date']['type'] = 'text'; reject_msc( static fn() => Schema::save( $invalid ), 'Populated field conversion requires explicit migration' );
// Local removal, global lifecycle, and restoration preserve ownership boundaries.
local_msc( $c, $id, array(), array(), 'removed' ); drain_msc();
check_msc( null === msc_local_post_id( $id, $c ) && null !== $s->get( $id ), 'Local removal preserves global record' );
local_msc( $c, $id, array(), array(), 'publish' ); drain_msc(); check_msc( null !== msc_local_post_id( $id, $c ) && 'Site C description' === msc_get_field( $id, 'content', $c ), 'Recreated local post retains overrides' );
$r = at_msc( $a, static fn() => Plugin::store()->update( $id, array(), (int) Plugin::store()->get( $id )['version'], 'trash' ) ); drain_msc();
foreach ( $sites as $site ) { check_msc( 'draft' === at_msc( $site, static fn() => get_post_status( msc_local_post_id( $id ) ) ), 'Global trash unpublishes site ' . $site ); }
at_msc( $a, static fn() => Plugin::store()->update( $id, array(), (int) $r['version'], 'active' ) ); drain_msc();
check_msc( 'publish' === at_msc( $b, static fn() => get_post_status( msc_local_post_id( $id ) ) ), 'Global restoration restores local publication preference' );
check_msc( count( at_msc( $a, static fn() => Plugin::store()->revisions( $id ) ) ) >= 5, 'Global revisions retained with audit information' );
at_msc( $a, static fn() => Plugin::store()->restore( $id, 1, (int) Plugin::store()->get( $id )['version'] ) ); drain_msc();
check_msc( '2027-01-01' === msc_get_global( $id, 'premiere_date' ) && 'Site C description' === msc_get_field( $id, 'content', $c ), 'Revision recovery changes globals while preserving local overrides' );
Schema::participate( $c, array( 'msc_episode' ) ); drain_msc(); check_msc( 'draft' === at_msc( $c, static fn() => get_post_status( msc_local_post_id( $id ) ) ), 'Disabling participation unpublishes local posts' );
Schema::participate( $c, array_keys( Schema::all() ) ); drain_msc();
// Failure/retry after a crash-like exception: mapping exists; repeat must not duplicate a post.
$crash = static function () { throw new RuntimeException( 'Injected integration failure' ); };
add_action( 'msc_materialized', $crash ); Queue::sync( $id, $a );
$result = Queue::run( 100 ); check_msc( $result['errors'] > 0, 'Synchronization failure recorded' );
remove_action( 'msc_materialized', $crash );
global $wpdb; $wpdb->query( 'UPDATE ' . Database::table( 'jobs' ) . " SET available_at=UTC_TIMESTAMP() WHERE state='pending'" );
$before = msc_local_post_id( $id, $a ); drain_msc(); check_msc( $before === msc_local_post_id( $id, $a ), 'Retry recovers without duplicate local post' );
add_action( 'msc_materialized', $crash ); Queue::sync( $id, $a );
for ( $attempt = 0; $attempt < 5; ++$attempt ) { $wpdb->query( 'UPDATE ' . Database::table( 'jobs' ) . " SET available_at=UTC_TIMESTAMP() WHERE state='pending'" ); Queue::run( 100 ); }
check_msc( Queue::status()['failed'] === 1, 'Five failed attempts move job to failed state' );
remove_action( 'msc_materialized', $crash ); Queue::retry(); drain_msc();
check_msc( Queue::status()['failed'] === 0, 'Network retry recovers exhausted job' );
// Cross the 100-row seed boundary with actual native post materialization.
$bulk = array();
at_msc( $a, static function () use ( &$bulk, $tag, $batch_size ) { for ( $n = 0; $n < $batch_size; ++$n ) { $r = Plugin::store()->create( 'msc_show', array( 'title' => 'Batch ' . $tag . ' ' . $n ) ); $bulk[] = (int) $r['id']; } } );
Schema::participate( $c, array( 'msc_episode' ) ); drain_msc(); Schema::participate( $c, array_keys( Schema::all() ) ); drain_msc();
foreach ( array( $bulk[0], $bulk[100], $bulk[ $batch_size - 1 ] ) as $record ) { check_msc( null !== msc_local_post_id( $record, $c ), 'Batched catch-up materializes record ' . $record ); }
check_msc( $batch_size === count( array_filter( $bulk, static fn( $record ) => null !== msc_local_post_id( $record, $c ) ) ), $batch_size . ' records survive participation catch-up across page boundaries' );
// Idempotent materialization and cache invalidation.
$before = msc_local_post_id( $id, $a ); Plugin::materializer()->sync( $id, $a ); Plugin::materializer()->sync( $id, $a ); check_msc( $before === msc_local_post_id( $id, $a ), 'Repeated synchronization reuses native post mapping' );
check_msc( at_msc( $a, static fn() => get_post( msc_local_post_id( $id ) )->post_title ) === msc_get_field( $id, 'title', $a ), 'Native post cache matches effective data after synchronization' );
WP_CLI::success( $GLOBALS['msc_checks'] . ' checks passed; WordPress ' . get_bloginfo( 'version' ) . ', PHP ' . PHP_VERSION . '; three subsites; ' . $batch_size . '-record batching exercise.' );
