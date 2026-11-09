<?php
/** Explicitly style only the four named local demo sites. */
wp_set_current_user( 1 );
if ( ! wp_get_theme( 'msc-screening-room' )->exists() ) {
	throw new RuntimeException( 'Screening Room theme is missing. Run the updated shared-content setup.' );
}
WP_Theme::network_enable_theme( 'msc-screening-room' );
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = array(
		'/site-a/' => 'a',
		'/site-b/' => 'b',
		'/site-c/' => 'c',
		'/site-d/' => 'd',
	)[ $site->path ] ?? null;
	if ( ! $key ) {
		continue;
	}
	switch_to_blog( (int) $site->blog_id );
	try {
		switch_theme( 'msc-screening-room' );
		update_option( 'msc_site_identity', $key );
		update_option( 'show_on_front', 'posts' );
		update_option( 'msc_flush_rewrite', 1 );
	} finally {
		restore_current_blog();
	}
}
WP_CLI::success( 'Sites A, B, C and D now use distinct demo styles and descriptive site labels.' );
