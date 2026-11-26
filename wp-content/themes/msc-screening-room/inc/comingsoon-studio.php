<?php
use MSC\{Attribution, EntityCatalog, MovieEditorial, NetworkSites, Presentation, ReviewScores, Reviews};

function msc_studio_designs(): array {
	return array( 'orbit' => 'Orbit', 'cut' => 'Cut Room', 'tower' => 'The Tower' );
}

function msc_studio_art( string $design ): string {
	return get_template_directory_uri() . '/assets/studio/' . $design . '.webp';
}

function msc_studio_previews( array $ids, string $kind, string $design ): ?WP_Post {
	$id = $ids[ $kind . '-preview-' . $design ] ?? 0;
	$page = $id ? get_post( $id ) : null;
	return $page && 'publish' === $page->post_status && ! $page->post_password ? $page : null;
}

function msc_studio_home( array $ids, string $design ): array {
	$designs = msc_studio_designs();
	$scenes = array();
	foreach ( array( $design => $designs[ $design ] ) + $designs as $key => $name ) {
		$story = msc_studio_previews( $ids, 'article', $key );
		if ( ! $story ) { continue; }
		$scenes[ $key ] = array(
			'title' => get_the_title( $story ), 'label' => array( 'orbit' => 'Black Noon', 'cut' => 'Spin Cycle', 'tower' => 'Thirteenth Landing' )[ $key ],
			'category' => 'Movie News', 'image' => msc_studio_art( $key ), 'text' => $story->post_excerpt,
			'url' => get_permalink( $story ), 'action' => 'Read the article',
		);
	}
	$movie = msc_studio_previews( $ids, 'movie-page', $design );
	$iron_man = msc_studio_iron_man();
	$art = $iron_man ? Presentation::artwork( $iron_man )['url'] ?? '' : '';
	if ( $movie ) {
		$scenes['iron-man'] = array( 'title' => 'Iron Man', 'label' => 'Iron Man', 'category' => 'Movie Guide', 'image' => $art, 'text' => 'Explore the film’s cast, trailers, reviews and ways to watch.', 'url' => get_permalink( $movie ), 'action' => 'Explore Iron Man' );
		if ( $art ) { Attribution::collect_image( $iron_man ); }
	}
	$scenes['calendar'] = array( 'title' => 'Upcoming Releases', 'label' => 'Release Dates', 'category' => 'Movies & TV', 'image' => get_template_directory_uri() . '/assets/comingsoon/station.webp', 'text' => 'Browse the Site A release schedule for movies, TV premieres and episodes.', 'url' => NetworkSites::calendar_url(), 'action' => 'Open the release calendar' );
	return $scenes;
}

function msc_studio_iron_man(): ?array {
	$movie = get_page_by_path( 'iron-man', OBJECT, 'msc_movie' );
	$record = $movie ? msc_from_post( $movie->ID ) : null;
	return $record ? Presentation::record( $record['id'] ) : null;
}

function msc_studio_movie( array $record ): array {
	$art = Presentation::artwork( $record )['url'] ?? '';
	$credits = EntityCatalog::rows( $record );
	$cast_uuid = $credits['actors']['items'][0]['uuid'] ?? '';
	$top_cast = $cast_uuid ? Presentation::record( $cast_uuid ) : null;
	$person_art = $top_cast ? Presentation::artwork( $top_cast, 'medium_large' )['url'] ?? '' : '';
	$values = array( 'record' => $record, 'credits' => $credits, 'scores' => ReviewScores::rows( $record ), 'watch' => MovieEditorial::watch( $record['data'] ) );
	$sections = array( 'overview' => 'Overview', 'trailers' => 'Trailers', 'watch' => 'Where to Watch', 'credits' => 'Cast & Crew', 'scores' => 'Review Scores', 'details' => 'Film Details' );
	$scenes = array();
	foreach ( $sections as $key => $label ) {
		if ( 'watch' === $key && ! $values['watch'] ) { continue; }
		ob_start();
		get_template_part( 'parts/movie-' . $key, null, $values );
		$body = trim( (string) ob_get_clean() );
		if ( ! $body ) { continue; }
		$image = 'credits' === $key && $person_art ? $person_art : $art;
		$scenes[ $key ] = array( 'title' => $label, 'label' => $label, 'image' => $image, 'body' => $body );
	}
	Attribution::collect( $record );
	Attribution::collect_image( $record );
	if ( $person_art ) { Attribution::collect_image( $top_cast ); }
	return $scenes;
}

function msc_studio_article( WP_Post $post, string $design ): array {
	$body = apply_filters( 'the_content', get_the_content( null, false, $post ) );
	$chunks = preg_split( '/(?=<h2\b)/i', $body, -1, PREG_SPLIT_NO_EMPTY ) ?: array( $body );
	$scenes = array();
	foreach ( $chunks as $index => $chunk ) {
		$label = preg_match( '/<h2\b[^>]*>(.*?)<\/h2>/is', $chunk, $matches ) ? wp_strip_all_tags( $matches[1] ) : 'The Story';
		$scenes[ 'chapter-' . ( $index + 1 ) ] = array( 'title' => $label, 'label' => $label, 'image' => msc_studio_art( $design ), 'body' => $chunk );
	}
	return $scenes;
}
