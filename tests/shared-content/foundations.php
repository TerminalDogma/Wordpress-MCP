<?php
/**
 * Regression tests for the 3.0 foundation fixes: typed errors, the queue lease,
 * cache-safe transients, schema memoization, autosaves, store and SEO fixes.
 *
 * Run with: sh bin/shared-content.sh test (disposable local network only).
 *
 * @package MultisiteSharedContent
 */

use MSC\{BusyError, ConflictError, Database, HttpError, NotFoundError, OmdbClient, OpenClient, PermissionError, Queue, Rest, Schema, Transients};

if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local network required.' );
}
wp_set_current_user( 1 );
$GLOBALS['foundation_checks'] = 0;

function foundation_check( $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['foundation_checks'];
	WP_CLI::log( 'PASS: ' . $message );
}

/** Run a callback and return the exception it throws, or null. */
function foundation_error( callable $callback ): ?Throwable {
	try {
		$callback();
	} catch ( Throwable $e ) {
		return $e;
	}
	return null;
}

// Typed errors carry their HTTP status; REST responses use it.
$second = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$key    = 'msc:' . md5( Database::table( 'foundation-test' ) );
foundation_check( '1' === (string) $second->get_var( $second->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ), 'A second connection can hold a test lock' );
try {
	$error = foundation_error( static fn() => Database::lock( 'foundation-test', static fn() => true, 0 ) );
	foundation_check( $error instanceof BusyError && 503 === $error->status, 'Lock contention raises a retryable BusyError (503)' );
	foundation_check( $error instanceof RuntimeException, 'BusyError still satisfies existing RuntimeException handlers' );
} finally {
	$second->get_var( $second->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	$second->close();
}
foundation_check( true === Database::lock( 'foundation-test', static fn() => true, 0 ), 'A free lock is taken without waiting' );
foreach ( array(
	array( new ConflictError( 'Conflict: changed.' ), 409 ),
	array( new BusyError(), 503 ),
	array( new PermissionError( 'No.' ), 403 ),
	array( new NotFoundError( 'Missing.' ), 404 ),
	array( new HttpError( 'Teapot.', 418 ), 418 ),
) as list( $exception, $status ) ) {
	$response = Rest::attempt(
		static function () use ( $exception ) {
			throw $exception;
		}
	);
	foundation_check( is_wp_error( $response ) && $status === $response->get_error_data()['status'], get_class( $exception ) . ' maps to HTTP ' . $status );
}
$response = Rest::attempt(
	static function () {
		throw new InvalidArgumentException( 'Bad input.' );
	}
);
foundation_check( 400 === $response->get_error_data()['status'], 'Invalid input maps to HTTP 400' );

// Fresh transient reads work with and without a persistent object cache.
$was_external = wp_using_ext_object_cache( true );
try {
	set_site_transient( 'msc_foundation_fresh', 'kept', 60 );
	foundation_check( 'kept' === Transients::fresh( 'msc_foundation_fresh' ), 'With an object cache, a fresh read returns the stored transient' );
	foundation_check( 'kept' === OpenClient::fresh( 'msc_foundation_fresh' ), 'Import previews survive fresh reads under an object cache' );
	foundation_check( 'kept' === OmdbClient::fresh_transient( 'msc_foundation_fresh' ), 'OMDb previews survive fresh reads under an object cache' );
	delete_site_transient( 'msc_foundation_fresh' );
} finally {
	wp_using_ext_object_cache( (bool) $was_external ); // Passing null would leave the flag on.
}
set_site_transient( 'msc_foundation_fresh', 'old', 60 );
foundation_check( 'old' === get_site_transient( 'msc_foundation_fresh' ), 'A transient is cached in memory after it is read' );
$second = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->update(
	$GLOBALS['wpdb']->sitemeta,
	array( 'meta_value' => maybe_serialize( 'new' ) ),
	array(
		'meta_key' => '_site_transient_msc_foundation_fresh',
		'site_id'  => get_current_network_id(),
	)
);
$second->close();
foundation_check( 'new' === Transients::fresh( 'msc_foundation_fresh' ), 'Without an object cache, a fresh read sees another request\'s write' );
delete_site_transient( 'msc_foundation_fresh' );

// Schemas are memoized per request but follow every change to the option.
$network = get_current_network_id();
$saved   = get_network_option( $network, 'msc_schemas' );
try {
	foundation_check( Schema::all() === $saved, 'Schema::all() returns the saved schemas' );
	$changed                       = $saved;
	$changed['msc_movie']['label'] = 'Foundation Movies';
	update_network_option( $network, 'msc_schemas', $changed );
	foundation_check( 'Foundation Movies' === Schema::all()['msc_movie']['label'], 'A saved schema change is visible immediately' );
	delete_network_option( $network, 'msc_schemas' );
	foundation_check( Schema::all() === Schema::defaults(), 'With no saved schemas the built-in defaults apply' );
} finally {
	update_network_option( $network, 'msc_schemas', $saved );
}
foundation_check( Schema::all() === $saved, 'Restored schemas are visible again' );

// Queue lease: a worker that dies during a job does not block the queue.
global $wpdb;
$jobs = Database::table( 'jobs' );
for ( $i = 0; $i < 50 && Queue::status()['pending']; ++$i ) {
	Queue::run( 1000 );
}
$site_a  = (int) get_id_from_blogname( 'site-a' );
$records = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT record_id FROM ' . Database::table( 'maps' ) . ' WHERE site_id=%d AND post_id IS NOT NULL ORDER BY record_id LIMIT 2', $site_a ) ) );
foundation_check( 2 === count( $records ), 'Two materialized records are available for queue tests' );
list( $dying, $healthy ) = $records;
Queue::sync( $dying, $site_a );
$child = 'add_action( "msc_materialized", static function () { exit( 3 ); } ); MSC\Queue::run( 1 );';
shell_exec( 'wp eval ' . escapeshellarg( $child ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
$row = $wpdb->get_row( $wpdb->prepare( "SELECT attempts,state,available_at>UTC_TIMESTAMP() AS leased FROM $jobs WHERE job_key=%s", "sync:$dying:$site_a" ), ARRAY_A );
foundation_check( $row && 1 === (int) $row['attempts'] && 'pending' === $row['state'] && '1' === (string) $row['leased'], 'A job whose worker died is leased instead of staying first in line' );
Queue::sync( $healthy, $site_a );
Queue::run( 100 );
foundation_check( null === $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $jobs WHERE job_key=%s", "sync:$healthy:$site_a" ) ), 'Later jobs still run while the dead job waits out its lease' );
$wpdb->query( $wpdb->prepare( "UPDATE $jobs SET attempts=5,available_at=UTC_TIMESTAMP() WHERE job_key=%s", "sync:$dying:$site_a" ) );
$reported = '';
$listener = static function ( $job, $error ) use ( &$reported ) {
	$reported = $error;
};
add_action( 'msc_job_failed', $listener, 10, 2 );
Queue::run( 100 );
remove_action( 'msc_job_failed', $listener, 10 );
$row = $wpdb->get_row( $wpdb->prepare( "SELECT state,last_error FROM $jobs WHERE job_key=%s", "sync:$dying:$site_a" ), ARRAY_A );
foundation_check( 'failed' === $row['state'] && str_contains( $row['last_error'], 'worker stopped' ), 'A job that kills the worker every time fails with an explanation' );
foundation_check( str_contains( $reported, 'worker stopped' ), 'msc_job_failed reports the failure' );
$wpdb->delete( $jobs, array( 'job_key' => "sync:$dying:$site_a" ) );

// The cron tick skips quietly while another worker holds the lock.
$second = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$worker = 'msc:' . md5( Database::table( 'worker' ) );
$second->get_var( $second->prepare( 'SELECT GET_LOCK(%s, 0)', $worker ) );
$started = microtime( true );
$error   = foundation_error( static fn() => do_action( 'msc_tick' ) );
$second->get_var( $second->prepare( 'SELECT RELEASE_LOCK(%s)', $worker ) );
$second->close();
foundation_check( null === $error && microtime( true ) - $started < 2, 'The cron tick does not wait for or fail on a busy worker lock' );

// Gutenberg autosaves return the current version, so the next save is not rejected.
$post_id = wp_insert_post(
	array(
		'post_type'   => 'msc_show',
		'post_title'  => 'Autosave check ' . wp_generate_uuid4(),
		'post_status' => 'draft',
		'post_author' => get_current_user_id(),
	)
);
$record  = msc_from_post( $post_id );
foundation_check( (bool) $record, 'A native draft creates a shared record' );
$version  = (int) MSC\Plugin::store()->mapping( (int) $record['id'] )['local_version'];
$autosave = new WP_REST_Request( 'POST', "/wp/v2/msc_show/$post_id/autosaves" );
$autosave->set_param( 'title', 'Autosaved title' );
$autosave->set_param( 'msc_expected_version', $version );
$response = rest_do_request( $autosave );
$state    = $response->get_data()['msc_state'] ?? null;
foundation_check( 200 === $response->get_status() && is_array( $state ), 'A draft autosave succeeds and returns msc_state' );
$save = new WP_REST_Request( 'POST', "/wp/v2/msc_show/$post_id" );
$save->set_param( 'title', 'Saved after autosave' );
$save->set_param( 'msc_expected_version', $state['local_version'] );
foundation_check( 200 === rest_do_request( $save )->get_status(), 'The next save with the returned version succeeds' );
$stale = new WP_REST_Request( 'POST', "/wp/v2/msc_show/$post_id" );
$stale->set_param( 'title', 'Stale save' );
$stale->set_param( 'msc_expected_version', $version );
foundation_check( 409 === rest_do_request( $stale )->get_status(), 'A genuinely stale save is still rejected' );
wp_update_post(
	array(
		'ID'          => $post_id,
		'post_status' => 'publish',
	)
);
$published = new WP_REST_Request( 'POST', "/wp/v2/msc_show/$post_id/autosaves" );
$published->set_param( 'title', 'Autosave of a published post' );
$response = rest_do_request( $published );
foundation_check( 200 === $response->get_status() && isset( $response->get_data()['msc_state']['local_version'] ), 'An autosave revision of a published post also returns msc_state' );

WP_CLI::success( $GLOBALS['foundation_checks'] . ' foundation checks passed.' );
