<?php
/** Broader enrollment is confined to explicit disposable regression tests. */
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local test installation required.' ); }
wp_set_current_user( 1 );
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) { if ( ! in_array( $site->path, array( '/site-a/', '/site-b/', '/site-c/' ), true ) ) { continue; } update_blog_option( (int) $site->blog_id, 'msc_episodes_as_children', false ); update_blog_option( (int) $site->blog_id, 'msc_game_platform_policy', 'all' ); \MSC\Schema::participate( (int) $site->blog_id, array_keys( \MSC\Schema::all() ) ); }
for ( $i = 0; $i < 200 && \MSC\Queue::status()['pending']; ++$i ) { $result = \MSC\Queue::run( 1000 ); if ( $result['errors'] || ! $result['processed'] ) { throw new RuntimeException( 'Test enrollment synchronization failed.' ); } }
