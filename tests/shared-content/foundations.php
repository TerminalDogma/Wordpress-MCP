<?php
/**
 * Regression tests for the 3.0 foundation fixes: typed errors, the queue lease,
 * cache-safe transients, schema memoization, autosaves, store and SEO fixes.
 *
 * Run with: sh bin/shared-content.sh test (disposable local network only).
 *
 * @package MultisiteSharedContent
 */

use MSC\{BusyError, ConflictError, Database, HttpError, NotFoundError, OmdbClient, OpenClient, PermissionError, Rest, Transients};

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

WP_CLI::success( $GLOBALS['foundation_checks'] . ' foundation checks passed.' );
