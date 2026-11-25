<?php
/** Install the ComingSoon preview and its fictional stories on Site A. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
if ( 'a' !== get_option( 'msc_site_identity' ) || 'msc-screening-room' !== get_template() ) {
	WP_CLI::error( 'Run this on ComingSoon (Site A) with the Screening Room theme active.' );
}

$variant_key = $args[0] ?? '';
if ( ! in_array( $variant_key, array( '', 'b', 'c' ), true ) ) { WP_CLI::error( 'Choose preview b or c.' ); }
\MSC\Database::lock( 'comingsoon-preview-' . get_current_blog_id(), static function () use ( $variant_key ): void {
	$slug = 'homepage-preview';
	$marker = '_msc_comingsoon_preview';
	$existing = get_page_by_path( $slug, OBJECT, array( 'page' ) );
	if ( $existing ? ! in_array( get_post_meta( $existing->ID, $marker, true ), array( 'building', 'home' ), true ) : url_to_postid( home_url( '/' . $slug . '/' ) ) ) {
		WP_CLI::error( 'A page already uses /' . $slug . '/. It was left intact.' );
	}
	$variant = null;
	$variant_slug = $slug . '-' . $variant_key;
	if ( $variant_key ) {
		$variant = get_page_by_path( $variant_slug, OBJECT, array( 'page' ) );
		$variants = 'b' === $variant_key ? array( 'b', 'violet' ) : array( $variant_key );
		$occupied = $variant
			? 'home' !== get_post_meta( $variant->ID, $marker, true ) || ! in_array( get_post_meta( $variant->ID, '_msc_comingsoon_palette', true ), $variants, true )
			: url_to_postid( home_url( '/' . $variant_slug . '/' ) );
		if ( $occupied ) { WP_CLI::error( 'A page already uses /' . $variant_slug . '/. It was left intact.' ); }
	}

	$insert = static function ( array $page ): int {
		$result = wp_insert_post( wp_slash( $page + array( 'post_type' => 'page', 'comment_status' => 'closed' ) ), true );
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		return (int) $result;
	};

	$parent = $existing ? $existing->ID : $insert( array(
		'post_title' => 'ComingSoon Homepage Preview',
		'post_name' => $slug,
		'post_status' => 'draft',
		'page_template' => 'page-comingsoon-preview.php',
		'meta_input' => array( $marker => 'building' ),
	) );

	$stories = array(
		array(
			'last-meridian', 'lead', 'First Look', 'meridian', 'Maya Bennett',
			'Inside The Last Meridian’s Spectacular Trip to the Edge of the Solar System',
			'On the set of the new sci-fi adventure, an orbital observatory and a very real eclipse take center stage.',
			'The first thing you notice inside the Meridian is the weight of the door. For her first science-fiction film, director Lena Ortiz asked the production team to build a working observatory set big enough for the cast to get lost in. Its view of an eclipse was created with a backlit disk and a wall of reflected light.',
			'The film follows an engineer sent to restore communications aboard a research station beyond Saturn. Ortiz keeps returning to the same question. What happens when the person best equipped to repair a machine no longer trusts what it is telling her?',
		),
		array(
			'harbor-nine', 'spotlight', 'TV Preview', 'harbor', 'Jamie Park',
			'Harbor Nine Brings the Small-Town Mystery Back to the Waterfront',
			'A vanished boat and a dispute over the only open dock set the new series in motion.',
			'Harbor Nine opens before sunrise, with a search crew walking the last dry pier in town. The tide has turned early, and the fishing boat they were waiting on has come back without its captain.',
			'The series spends its first hour with the people who knew the captain by different names. Its most convincing detail is how quickly a rumor can cross a harbor, even before anyone has looked at the water.',
		),
		array(
			'celeste-rowe', 'spotlight', 'Interview', 'interview', 'Nina Cole',
			'Celeste Rowe on the Scene She Asked to Rewrite',
			'The actor talks through the quiet confrontation at the center of The Long Way Home.',
			'Celeste Rowe had one request before filming the diner scene in The Long Way Home. She wanted the argument to happen while her character was trying to make change for a customer. The whole point, she says, was to give the character something ordinary to do.',
			'That small revision changed where the camera would sit, where each actor would look, and how long the silence would last. In the finished film, the audience has enough time to notice that no one has touched their coffee.',
		),
		array(
			'second-signal-trailer', 'latest', 'Trailers', 'signal', 'Alex Mercer',
			'Second Signal’s First Trailer Hides Its Best Clue in Plain Sight',
			'The thriller’s first footage revolves around a voice in a late-night recording.',
			'The trailer for Second Signal follows a sound editor isolating a voice from the background of a routine recording. The voice belongs to him, but the conversation has yet to happen.',
			'Listen to the moment the subway doors close. The same sound turns up earlier in the trailer inside a scene that appears to take place several miles away.',
		),
		array(
			'meridian-effects', 'latest', 'Movies', 'station', 'Maya Bennett',
			'The Last Meridian Built Its Space Station by Hand',
			'A workshop visit reveals the miniature behind the film’s biggest shot.',
			'The model of the Meridian is short enough to sit on a workbench. Under a macro lens, the seams in its hull create the scale of a sprawling research station.',
			'The team filmed each rotation at two speeds, using reflected light to tie the exterior of the station to the hand-built observatory inside.',
		),
		array(
			'winter-television', 'latest', 'TV', 'apartment', 'Jamie Park',
			'The Quiet Hours Will Return for a Second Season',
			'The drama will revisit the apartment building after a winter that changed several lives.',
			'The Quiet Hours has been renewed for a second season. The first season spent nearly as much time in the building’s stairwell as in any of its apartments.',
			'The new episodes will follow a new tenant. Several characters from the first season are expected to return, although a few have since moved to other parts of the city.',
		),
		array(
			'road-comedy', 'latest', 'Movie News', 'diner', 'Nina Cole',
			'The Long Way Home Sets a Limited Theatrical Run',
			'The road comedy will open in a handful of cities ahead of a wider release.',
			'The Long Way Home follows two relatives who take a wrong turn trying to return a set of car keys. The detour takes them to a roadside diner that neither of them remembers quite the same way.',
			'The limited release will start with screenings in cities where the production shot, with a broader theatrical rollout planned after the first weekend.',
		),
		array(
			'long-way-home-review', 'watch', 'Movie Review', 'diner', 'Nina Cole',
			'The Long Way Home Finds Its Rhythm in the Detours',
			'A road comedy with room for small gestures and genuinely awkward silences.',
			'There is a scene late in The Long Way Home when a long explanation is interrupted by a waitress asking who ordered the pie. The movie is very good at letting life carry on in the background.',
			'The diner sequence is the center of the film, but the smaller pleasures are out on the road. Rowe does some of her best work from the passenger seat, with little to do beyond choosing whether to finish a sentence.',
		),
		array(
			'quiet-hours-streaming', 'watch', 'Streaming', 'apartment', 'Jamie Park',
			'Start With The Quiet Hours This Weekend',
			'The six-episode drama saves its most surprising turn for a neighborly visit.',
			'The first episode of The Quiet Hours follows an apartment resident delivering a package to the wrong floor. The rest of the series tracks the small changes that follow the visit.',
			'At six episodes, the season leaves room for a full weekend viewing without asking you to memorize a cast list. The first two installments make a good pair.',
		),
		array(
			'second-signal-review', 'watch', 'TV Review', 'signal', 'Alex Mercer',
			'Second Signal Makes a Good Case for Watching With Headphones',
			'A thriller that rewards listening closely, especially when no one is speaking.',
			'Second Signal treats its sound mix as part of the plot. A recording that appears straightforward in the premiere changes shape when a later episode lets us hear the room around it.',
			'The effect can feel demanding at first. By the third episode, the show has trained you to hear patterns in small sounds, and the moment those patterns stop is often its most suspenseful beat.',
		),
	);

	$stories = array_merge( $stories, require __DIR__ . '/comingsoon-preview-stories.php' );
	foreach ( $stories as $position => list( $name, $section, $label, $art, $byline, $title, $deck, $first, $second ) ) {
		$found = get_page_by_path( $slug . '/' . $name, OBJECT, array( 'page' ) );
		if ( $found ) {
			if ( 'story' !== get_post_meta( $found->ID, $marker, true ) ) {
				WP_CLI::warning( 'The path /' . $slug . '/' . $name . '/ is in use. That page was left intact.' );
			}
			continue;
		}
		$date = current_datetime()->modify( sprintf( '-%d minutes', ( $position + 1 ) * 41 ) );
		$insert( array(
			'post_title' => $title,
			'post_name' => $name,
			'post_parent' => $parent,
			'post_status' => 'publish',
			'post_date' => $date->format( 'Y-m-d H:i:s' ),
			'post_excerpt' => $deck,
			'post_content' => '<p>' . esc_html( $first ) . '</p><p>' . esc_html( $second ) . '</p>',
			'page_template' => 'page-comingsoon-preview-story.php',
			'menu_order' => $position,
			'meta_input' => array(
				$marker => 'story',
				'_msc_comingsoon_section' => $section,
				'_msc_comingsoon_label' => $label,
				'_msc_comingsoon_art' => $art,
				'_msc_comingsoon_byline' => $byline,
			),
		) );
	}
	if ( 'building' === get_post_meta( $parent, $marker, true ) ) {
		$result = wp_update_post( array( 'ID' => $parent, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		update_post_meta( $parent, $marker, 'home' );
	}
	if ( ! $variant_key ) {
		WP_CLI::success( 'ComingSoon homepage preview: ' . get_permalink( $parent ) );
		return;
	}
	$variant_label = strtoupper( $variant_key );
	$variant_id = $variant ? $variant->ID : $insert( array(
		'post_title' => 'ComingSoon Homepage Preview ' . $variant_label,
		'post_name' => $variant_slug,
		'post_status' => 'publish',
		'page_template' => 'page-comingsoon-preview.php',
		'meta_input' => array(
			$marker => 'home',
			'_msc_comingsoon_story_source' => $parent,
			'_msc_comingsoon_palette' => $variant_key,
		),
	) );
	if ( $variant ) {
		update_post_meta( $variant_id, '_msc_comingsoon_story_source', $parent );
		update_post_meta( $variant_id, '_msc_comingsoon_palette', $variant_key );
	}
	WP_CLI::success( 'ComingSoon homepage preview ' . $variant_label . ': ' . get_permalink( $variant_id ) );
} );
