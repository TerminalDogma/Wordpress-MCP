<?php
/** Install the new Site A concepts and retire the previous experimental pages. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( 'a' !== get_option( 'msc_site_identity' ) || 'msc-screening-room' !== get_template() ) {
	WP_CLI::error( 'Run this on ComingSoon (Site A) with the Screening Room theme active.' );
}

$articles = array(
	'orbit' => array(
		'Orbit', 'Black Noon Sets a One-Night Planetarium Release',
		'The science-fiction film will play on domed screens with a live introduction from its effects team.',
		<<<'HTML'
<p><em>Black Noon</em> will play at five planetariums on December 12. The science-fiction film follows a maintenance crew sent to inspect a ring-shaped research station during an eclipse. The screenings are part of a limited engagement designed to show the film’s overhead effects on a domed screen.</p>
<p>The feature presentation will use the same story and running time as the theatrical cut. The production added a separate image track for the top of the dome, drawn from footage shot during the original filming. An on-screen introduction will explain when that track is visible.</p>

<h2>How the dome version works</h2>
<p>The action stays in front of the audience for most of the film. During the eclipse sequence, the station’s lights move overhead while its crew remains visible in the main frame. The lighting team built a full-size doorway and a small partial model of the station, then filmed both against the same lighting cues.</p>
<p>Because the five venues use different projectors, the production tested a separate version at each location. The transitions are timed to match the theatrical soundtrack. Viewers can follow the story without looking up, and the wide effects do not contain extra dialogue or plot details.</p>

<h2>Where to see Black Noon</h2>
<p>The screenings are scheduled in Chicago, Denver, Philadelphia, Portland, and Seattle. The venue listings will carry their own times, seating information, and accessibility options. Some venues will open early for a live discussion with a member of the effects team.</p>
<p>The distributor is handling tickets through the venues. A conventional theatrical release is planned for the following month. A home release date has yet to be announced.</p>
HTML,
	),
	'cut' => array(
		'Cut Room', 'Spin Cycle Adds Six Cities to Its Theatrical Run',
		'The laundromat comedy expands in December with two special screenings featuring its cast.',
		<<<'HTML'
<p><em>Spin Cycle</em> will expand to six cities beginning December 4. The comedy follows two sisters who discover that the last load at their neighborhood laundromat contains something neither of them owns. The first run played at independent theaters in New York and Los Angeles.</p>
<p>The film takes place over one rainy night and was shot in a working laundromat. Filming took place after the storefront closed, with the old front-loading machines left in place. Several scenes had to be filmed before the windows fogged over.</p>

<h2>The new cities and cast screenings</h2>
<p>The expansion adds Baltimore, Chicago, Minneapolis, New Orleans, Philadelphia, and San Francisco. Opening-night screenings in Chicago and Philadelphia will feature conversations with the cast. Both discussions follow the film and are included with admission.</p>
<p>The other venues have scheduled matinees as well as evening shows. The distributor plans to review the first weekend before confirming additional bookings, so each theater will publish its own schedule. Tickets are being sold directly by the participating theaters.</p>

<h2>What comes after theaters</h2>
<p>A digital release is planned for next year. The distributor has yet to set a specific date or announce a streaming service. The film will remain available in the first two cities during the December expansion.</p>
<p>The production has also assembled a short feature about the laundromat set. It includes rehearsal footage and shows how the crew framed the actors through an empty washer door. The short is still being prepared and will not accompany the December screenings.</p>
HTML,
	),
	'tower' => array(
		'The Tower', 'The Thirteenth Landing Will Screen Inside a Museum',
		'The mystery film will premiere in the building that inspired its unusual stairwell set.',
		<<<'HTML'
<p><em>The Thirteenth Landing</em> will premiere at a converted municipal museum on December 19. The film follows a late-shift courier who accepts a package for a room that does not appear on the building directory. The premiere location inspired the stairwell that appears throughout the film.</p>
<p>The production filmed exterior shots at the museum and built its own interior on a soundstage. That allowed the crew to move landings between shots while leaving subtle differences in the color and shape of the tiles. The changes become more obvious as the courier approaches the destination.</p>

<h2>What the museum will show</h2>
<p>The screening will take place in the main auditorium. An exhibition in the entrance gallery will include a partial stair model, early floor plans, a courier bag, and reproductions of the tile samples used on the set. The exhibition will open before the screening and remain available after it ends.</p>
<p>The event will also include a discussion with the production designer. The designer will compare sections of the real building with the plans for the film set and explain which elements could be moved without changing the lighting.</p>

<h2>Tickets and wider release</h2>
<p>Tickets for the premiere are available through the museum. The auditorium and exhibition are accessible through the ground-floor entrance, and the museum will post information about captioned screenings with its listings. Admission to the exhibition is included with an event ticket.</p>
<p>A conventional theatrical release will follow in January. The distributor plans to publish the list of cities and venues closer to the opening date. No home release has been scheduled.</p>
HTML,
	),
);

$ids = \MSC\Database::lock( 'comingsoon-studio-previews-' . get_current_blog_id(), static function () use ( $articles ): array {
	$marker = '_msc_studio_preview';
	$ids = $pending = array();
	foreach ( $articles as $design => list( $label, $title, $deck, $body ) ) {
		foreach ( array( 'homepage' => 'page', 'movie-page' => 'page', 'article' => 'post' ) as $kind => $type ) {
			$slug = $kind . '-preview-' . $design;
			$existing = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
			$occupant = url_to_postid( home_url( '/' . $slug . '/' ) );
			if ( ( $existing && ( $type !== $existing->post_type || $slug !== get_post_meta( $existing->ID, $marker, true ) ) ) || ( $occupant && ( ! $existing || $occupant !== $existing->ID ) ) ) {
				throw new RuntimeException( 'Another entry uses /' . $slug . '/.' );
			}
			if ( $existing ) { $ids[ $slug ] = $existing->ID; continue; }
			$pending[ $slug ] = array(
				'post_type' => $type,
				'post_name' => $slug,
				'post_title' => match ( $kind ) { 'article' => $title, 'movie-page' => 'Iron Man · ' . $label, default => 'ComingSoon · ' . $label },
				'post_status' => 'draft',
				'post_content' => 'article' === $kind ? $body : '',
				'post_excerpt' => 'article' === $kind ? $deck : '',
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'page_template' => 'template-comingsoon-studio.php',
				'meta_input' => array( $marker => $slug, '_msc_studio_kind' => $kind, '_msc_studio_design' => $design ),
			);
		}
	}
	foreach ( $pending as $slug => $entry ) {
		$id = wp_insert_post( wp_slash( $entry ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
		$ids[ $slug ] = $id;
	}
	ksort( $ids );
	update_option( 'msc_comingsoon_studio_previews', $ids );
	if ( $ids !== get_option( 'msc_comingsoon_studio_previews' ) ) { throw new RuntimeException( 'Could not save the preview list.' ); }
	foreach ( $ids as $slug => $id ) {
		if ( 'publish' !== get_post_status( $id ) ) {
			$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		}
		if ( $slug !== get_post_field( 'post_name', $id ) ) { throw new RuntimeException( 'Could not assign /' . $slug . '/.' ); }
	}
	foreach ( array( 'signal', 'zine', 'disc' ) as $retired ) {
		foreach ( array( 'homepage' => 'page', 'movie-page' => 'page', 'article' => 'post' ) as $kind => $type ) {
			$slug = $kind . '-preview-' . $retired;
			$post = get_page_by_path( $slug, OBJECT, array( $type ) );
			if ( ! $post || 'publish' !== $post->post_status ) { continue; }
			$owned = 'homepage' === $kind
				? 'home' === get_post_meta( $post->ID, '_msc_comingsoon_preview', true ) && $retired === get_post_meta( $post->ID, '_msc_comingsoon_palette', true )
				: $slug === get_post_meta( $post->ID, '_msc_comingsoon_detail_preview', true );
			if ( ! $owned ) { continue; }
			$result = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true );
			if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		}
	}
	return $ids;
} );

foreach ( $ids as $id ) { WP_CLI::line( get_permalink( $id ) ); }
