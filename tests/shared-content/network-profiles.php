<?php
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Disposable local installation required.' );
}
wp_set_current_user( 1 );
$rows = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = \MSC\NetworkSites::key( $site->path );
	if ( ! $key ) {
		continue;
	}
	$p                      = \MSC\NetworkSites::profiles()[ $key ];
	$rows[ $site->blog_id ] = array(
		'types'    => $p['types'],
		'policy'   => $p['policy'],
		'children' => true,
	);
}
\MSC\NetworkSites::apply( $rows );
for ( $i = 0; $i < 200 && \MSC\Queue::status()['pending']; ++$i ) {
	$r = \MSC\Queue::run( 1000 );
	if ( $r['errors'] || ! $r['processed'] ) {
		throw new RuntimeException( 'Profile synchronization failed.' );
	}
}
