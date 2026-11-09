<?php
/**
 * Site transient helpers.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class Transients {
	/**
	 * Read a site transient that another request may have just written.
	 *
	 * Without a persistent object cache, site transients are network options and
	 * this request may hold a stale in-memory copy, so that copy is dropped first.
	 * With a persistent cache the cache is the only store, so it is never cleared.
	 *
	 * @param string $name Transient name.
	 * @return mixed The value, or false.
	 */
	public static function fresh( string $name ) {
		if ( ! wp_using_ext_object_cache() ) {
			$network = get_current_network_id();
			foreach ( array( '_site_transient_' . $name, '_site_transient_timeout_' . $name, 'notoptions' ) as $option ) {
				wp_cache_delete( $network . ':' . $option, 'site-options' );
			}
		}
		return get_site_transient( $name );
	}
}
