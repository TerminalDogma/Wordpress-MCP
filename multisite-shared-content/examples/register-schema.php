<?php
/**
 * Run this once from authorized setup code AFTER MSC tables exist (e.g. an admin action).
 * Do not run on every frontend request. This adds a schema; participation is still explicit.
 */
function my_network_add_shared_movie_type(): void {
	$schemas = \MSC\Schema::all();
	$schemas['msc_movie'] = array(
		'label' => 'Movies', 'slug' => 'movies', 'archive' => true, 'noindex' => true, 'default_status' => 'draft',
		'fields' => array(
			'title' => array( 'type' => 'text', 'property' => 'post_title' ),
			'content' => array( 'type' => 'richtext', 'property' => 'post_content' ),
			'release_date' => array( 'type' => 'date', 'indexed' => true ),
			'runtime' => array( 'type' => 'integer', 'min' => 0 ),
			'poster' => array( 'type' => 'media' ),
		),
	);
	\MSC\Schema::save( $schemas );
}
