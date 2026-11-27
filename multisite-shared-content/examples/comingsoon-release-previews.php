<?php
/** Install both compact release placements for the original three previews. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( 'a' !== get_option( 'msc_site_identity' ) || 'msc-screening-room' !== get_template() ) {
	WP_CLI::error( 'Run this on ComingSoon (Site A) with the Screening Room theme active.' );
}

$ids = \MSC\Database::lock( 'comingsoon-release-previews-' . get_current_blog_id(), static function (): array {
	$source = get_page_by_path( 'homepage-preview', OBJECT, array( 'page' ) );
	if ( ! $source || 'publish' !== $source->post_status || 'home' !== get_post_meta( $source->ID, '_msc_comingsoon_preview', true ) ) {
		throw new RuntimeException( 'Install the shared ComingSoon preview stories first.' );
	}

	$ids = $pending = array();
	foreach ( array( 'a', 'b', 'c' ) as $palette ) {
		foreach ( array( 'top' => 'Near Top', 'inline' => 'Current Position' ) as $placement => $label ) {
			$slug = 'homepage-preview-' . $palette . '-releases-' . $placement;
			$page = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
			$meta = array(
				'_msc_comingsoon_preview' => 'home',
				'_msc_comingsoon_palette' => $palette,
				'_msc_comingsoon_release_placement' => $placement,
			);
			$occupied = url_to_postid( home_url( '/' . $slug . '/' ) );
			if ( ( $page && 'page' !== $page->post_type ) || ( $occupied && ( ! $page || $occupied !== $page->ID ) ) ) {
				throw new RuntimeException( 'Another entry uses /' . $slug . '/.' );
			}
			if ( $page ) {
				foreach ( $meta as $key => $value ) {
					if ( $value !== get_post_meta( $page->ID, $key, true ) ) { throw new RuntimeException( 'Another entry uses /' . $slug . '/.' ); }
				}
				$ids[ $slug ] = $page->ID;
				continue;
			}

			$pending[ $slug ] = array(
				'post_type' => 'page', 'post_name' => $slug, 'post_status' => 'publish',
				'post_title' => 'ComingSoon Homepage Preview ' . strtoupper( $palette ) . ' · Compact Releases ' . $label,
				'comment_status' => 'closed', 'ping_status' => 'closed',
				'page_template' => 'page-comingsoon-preview.php',
				'meta_input' => $meta + array( '_msc_comingsoon_story_source' => $source->ID ),
			);
		}
	}
	foreach ( $pending as $slug => $entry ) {
		$id = wp_insert_post( wp_slash( $entry ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		if ( $slug !== get_post_field( 'post_name', $id ) ) { throw new RuntimeException( 'Could not assign /' . $slug . '/.' ); }
		$ids[ $slug ] = $id;
	}
	foreach ( $ids as $id ) { update_post_meta( $id, '_msc_comingsoon_story_source', $source->ID ); }
	ksort( $ids );
	return $ids;
} );

foreach ( $ids as $id ) { WP_CLI::line( get_permalink( $id ) ); }
