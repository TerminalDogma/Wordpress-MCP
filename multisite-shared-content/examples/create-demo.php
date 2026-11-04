<?php
/** Optional fictitious demo. Existing demo records are retained on subsequent runs. */
wp_set_current_user( 1 );
$sites = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( in_array( $site->path, array( '/site-a/', '/site-b/', '/site-c/' ), true ) ) { $sites[] = (int) $site->blog_id; } }
if ( count( $sites ) !== 3 ) { throw new RuntimeException( 'Run shared-content setup first.' ); }
$store = \MSC\Plugin::store();
$demo = get_network_option( get_current_network_id(), 'msc_demo_records', array() );
if ( empty( $demo['show'] ) || ! $store->get( $demo['show'] ) ) {
	switch_to_blog( $sites[0] );
	try {
		$show = $store->create( 'msc_show', array( 'title' => 'Network Demo Show', 'content' => '<p>A fictitious show for testing shared content.</p>', 'network' => 'Demo Network', 'premiere_date' => '2099-01-01' ) );
		$episode = $store->create( 'msc_episode', array( 'title' => 'Demo Pilot', 'parent_show' => $show['uuid'], 'season_number' => 1, 'episode_number' => 1, 'release_date' => '2099-01-01', 'air_time' => '20:00:00', 'time_zone' => 'America/New_York' ) );
		$demo = array( 'show' => $show['uuid'], 'episode' => $episode['uuid'] );
	} finally { restore_current_blog(); }
	for ( $i = 0; $i < 100 && \MSC\Queue::status()['pending']; ++$i ) { $result = \MSC\Queue::run( 1000 ); if ( $result['errors'] ) { throw new RuntimeException( 'Demo synchronization failed.' ); } }
	foreach ( $sites as $site ) {
		switch_to_blog( $site );
		try { foreach ( $demo as $uuid ) { $r = $store->resolved( $uuid ); $store->local( $uuid, array(), array(), (int) $r['mapping']['local_version'], 'publish' ); } }
		finally { restore_current_blog(); }
	}
	switch_to_blog( $sites[1] );
	try { $r = $store->resolved( $demo['episode'] ); $store->local( $r['uuid'], array( 'release_date' => '2099-01-02', 'seo_title' => 'Site B episode guide' ), array(), (int) $r['mapping']['local_version'] ); }
	finally { restore_current_blog(); }
	update_network_option( get_current_network_id(), 'msc_demo_records', $demo );
}
// Add the Movie example when upgrading an existing demo; retain TV edits.
if ( empty( $demo['movie'] ) || ! $store->get( $demo['movie'] ) ) {
	switch_to_blog( $sites[0] );
	try { $movie = $store->create( 'msc_movie', array( 'title' => 'Network Demo Movie', 'content' => '<p>A fictitious film for testing movie-specific fields.</p>', 'release_date' => '2099-06-01', 'digital_release_date' => '2099-07-01', 'runtime' => 112, 'director' => 'Demo Director', 'distributor' => 'Demo Pictures', 'cast' => '<p>Demo Actor as the lead</p>' ) ); $demo['movie'] = $movie['uuid']; }
	finally { restore_current_blog(); }
	for ( $i = 0; $i < 100 && \MSC\Queue::status()['pending']; ++$i ) { $result = \MSC\Queue::run( 1000 ); if ( $result['errors'] ) { throw new RuntimeException( 'Movie demo synchronization failed.' ); } }
	foreach ( $sites as $site ) {
		switch_to_blog( $site );
		try { $r = $store->resolved( $demo['movie'] ); $store->local( $r['uuid'], array(), array(), (int) $r['mapping']['local_version'], 'publish' ); }
		finally { restore_current_blog(); }
	}
	update_network_option( get_current_network_id(), 'msc_demo_records', $demo );
}
foreach ( $sites as $site ) {
	switch_to_blog( $site );
	try { $r = $store->resolved( $demo['episode'] ); WP_CLI::log( home_url( '/' ) . ' | Episode date: ' . $r['data']['release_date'] . ' | ' . admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] ) ); }
	finally { restore_current_blog(); }
	switch_to_blog( $site );
	try { WP_CLI::log( 'Movie fields: ' . admin_url( 'admin.php?page=msc-acf&record=' . $demo['movie'] ) ); }
	finally { restore_current_blog(); }
}
WP_CLI::success( 'TV Show, Episode and Movie demos ready. Site B has its own episode date; Sites A and C inherit it.' );
