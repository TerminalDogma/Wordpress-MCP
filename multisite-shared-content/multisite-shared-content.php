<?php
/**
 * Plugin Name: Multisite Shared Content
 * Description: Network records, native site posts, and explicit field-level inheritance.
 * Version: 1.4.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Network: true
 * License: GPL-2.0-or-later
 * Text Domain: multisite-shared-content
 */
defined( 'ABSPATH' ) || exit;
define( 'MSC_FILE', __FILE__ );
define( 'MSC_VERSION', '1.4.0' );
spl_autoload_register( static function ( $class ) {
	if ( str_starts_with( $class, 'MSC\\' ) ) {
		$file = __DIR__ . '/src/' . substr( $class, 4 ) . '.php';
		if ( is_file( $file ) ) { require_once $file; }
	}
} );
require_once __DIR__ . '/src/functions.php';
register_activation_hook( __FILE__, array( 'MSC\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSC\\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'MSC\\Plugin', 'boot' ) );
