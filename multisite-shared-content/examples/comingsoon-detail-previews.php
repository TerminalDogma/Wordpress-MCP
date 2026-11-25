<?php
/** Install standalone ComingSoon movie and news design previews. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( 'a' !== get_option( 'msc_site_identity' ) || 'msc-screening-room' !== get_template() ) {
	WP_CLI::error( 'Run this on ComingSoon (Site A) with the Screening Room theme active.' );
}

$article = <<<'HTML'
<p><em>The Last Meridian</em> will return to select IMAX theaters for one week beginning Friday, December 11. Tickets are on sale through participating theaters. The science-fiction film follows an observatory crew that intercepts a signal during a solar eclipse and has to decide whether to leave its station to investigate.</p>

<p>The return engagement uses the same cut shown during the film’s original theatrical run. It comes after several months of home availability and gives viewers another chance to see the eclipse sequence on a large screen. Much of that sequence was filmed inside a full-scale observatory set, with a moving light rig used to change what the actors could see through its windows.</p>

<h2>Where to see The Last Meridian in IMAX</h2>

<p>Participating theaters will list individual showtimes on their websites. The release is limited to select markets, so availability varies by city, and some locations may schedule only one screening per day. The engagement is scheduled to end Thursday, December 17. Check the theater’s listing for the screening format and available accessibility options before booking.</p>

<p>A short production featurette will play before the film at participating locations. It follows the crew as they rehearse the observatory sequence and build the eclipse lighting around the camera’s movement. The featurette will run as part of the ticketed program, with no separate reservation required.</p>

<p>The IMAX run arrives before the film’s previously announced home-video edition, which is still scheduled for early next year. The production featurette has not been announced for that edition. The film will also remain available to rent digitally during the theatrical engagement.</p>

<p>For viewers planning a group screening, theaters are handling ticket sales and seat reservations individually. Any additional showtimes will appear on the same local listings if demand warrants them.</p>
HTML;

$ids = \MSC\Database::lock( 'comingsoon-detail-previews-' . get_current_blog_id(), static function () use ( $article ): array {
	$marker = '_msc_comingsoon_detail_preview';
	$pages = array();
	$ids = array();
	foreach ( array( 'a', 'b', 'c' ) as $variant ) {
		foreach ( array( 'movie-page-preview-' => 'page', 'article-preview-' => 'post' ) as $prefix => $type ) {
			$slug = $prefix . $variant;
			$existing = get_page_by_path( $slug, OBJECT, $type );
			$other = get_page_by_path( $slug, OBJECT, 'page' === $type ? 'post' : 'page' );
			$occupant = url_to_postid( home_url( '/' . $slug . '/' ) );
			if ( $other || ( $existing && $slug !== get_post_meta( $existing->ID, $marker, true ) ) || ( $occupant && ( ! $existing || $occupant !== $existing->ID ) ) ) {
				throw new RuntimeException( 'Another entry uses /' . $slug . '/.' );
			}
			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}
			$is_article = 'post' === $type;
			$pages[ $slug ] = array(
				'post_type' => $type,
				'post_title' => $is_article ? 'The Last Meridian Sets a One-Week Return to IMAX' : 'Iron Man · Movie Page Preview ' . strtoupper( $variant ),
				'post_name' => $slug,
				'post_status' => 'draft',
				'post_content' => $is_article ? $article : '',
				'post_excerpt' => $is_article ? 'The sci-fi film will play select theaters in December with a new featurette about its eclipse sequence.' : '',
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'page_template' => 'template-comingsoon-detail-preview.php',
				'meta_input' => array( $marker => $slug, '_msc_comingsoon_detail_variant' => $variant ) + ( $is_article ? array(
					'_msc_comingsoon_art' => 'meridian',
					'_msc_comingsoon_byline' => 'Maya Bennett',
				) : array() ),
			);
		}
	}
	foreach ( $pages as $slug => $entry ) {
		$id = wp_insert_post( wp_slash( $entry ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$ids[ $slug ] = $id;
	}
	ksort( $ids );
	update_option( 'msc_comingsoon_detail_previews', $ids );
	if ( $ids !== get_option( 'msc_comingsoon_detail_previews' ) ) { throw new RuntimeException( 'Could not save the preview list.' ); }
	foreach ( $ids as $slug => $id ) {
		if ( 'publish' !== get_post_status( $id ) ) {
			$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		}
		if ( $slug !== get_post_field( 'post_name', $id ) ) { throw new RuntimeException( 'Could not assign /' . $slug . '/.' ); }
	}
	return $ids;
} );

foreach ( $ids as $id ) { WP_CLI::line( get_permalink( $id ) ); }
