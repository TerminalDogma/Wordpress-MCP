<?php
/** Shared rendering for the ComingSoon preview and its sample story pages. */
function msc_cs_preview_meta( WP_Post $post, string $key ): string {
	return (string) get_post_meta( $post->ID, '_msc_comingsoon_' . $key, true );
}

function msc_cs_preview_format( WP_Post $story ): string {
	return match ( msc_cs_preview_meta( $story, 'label' ) ) {
		'TV Recap' => 'recaps',
		'Movie Review', 'TV Review', 'Criticism' => 'criticism',
		'Interview', 'Feature', 'Behind the Scenes' => 'features',
		'Streaming', 'Streaming Guide', 'What to Stream' => 'streaming',
		'Trailers', 'Trailer Breakdown', 'Teaser Breakdown', 'First Look' => 'trailers',
		default => 'news',
	};
}

function msc_cs_preview_art( WP_Post $story, bool $priority = false ): void {
	if ( has_post_thumbnail( $story ) ) {
		echo get_the_post_thumbnail( $story, 'large', array( 'class' => 'cs-art', 'alt' => '', 'loading' => $priority ? 'eager' : 'lazy' ) );
		return;
	}
	$art = msc_cs_preview_meta( $story, 'art' );
	if ( ! in_array( $art, array( 'meridian', 'harbor', 'interview', 'diner', 'apartment', 'signal', 'station', 'switchboard', 'hollis', 'cinematographer', 'western', 'projectionist', 'breakwater' ), true ) ) { return; }
	$base = get_template_directory_uri() . '/assets/comingsoon/';
	$attrs = ' width="508" height="508"';
	if ( 'meridian' === $art ) {
		$attrs = ' width="1440" height="960" srcset="' . esc_attr( $base . 'meridian-small.webp 760w, ' . $base . 'meridian.webp 1440w' ) . '" sizes="(max-width: 680px) 94vw, (max-width: 1200px) 70vw, 900px"';
	}
	echo '<img class="cs-art cs-art--' . esc_attr( $art ) . '" src="' . esc_url( $base . $art . '.webp' ) . '" alt="" decoding="async" ' . ( $priority ? 'fetchpriority="high"' : 'loading="lazy"' ) . $attrs . '>';
}

function msc_cs_preview_card( WP_Post $story, string $style, bool $hidden = false ): void {
	$deck = get_post_field( 'post_excerpt', $story );
	$byline = msc_cs_preview_meta( $story, 'byline' );
	$feed = 'feed' === $style;
	?>
	<article class="cs-card cs-card--<?php echo esc_attr( $style ); ?>"<?php if ( $feed ) { ?> data-cs-format="<?php echo esc_attr( msc_cs_preview_format( $story ) ); ?>"<?php } ?><?php if ( $hidden ) { ?> hidden<?php } ?>>
		<a class="cs-card-link" href="<?php echo esc_url( get_permalink( $story ) ); ?>">
			<?php if ( ! in_array( $style, array( 'brief', 'desk-brief', 'feed' ), true ) ) { msc_cs_preview_art( $story, 'lead' === $style ); } ?>
			<div class="cs-card-copy">
				<p class="cs-label"><?php echo esc_html( msc_cs_preview_meta( $story, 'label' ) ); ?>
					<?php if ( 'brief' === $style || $feed ) { ?><time datetime="<?php echo esc_attr( get_post_time( DATE_W3C, true, $story ) ); ?>"><?php echo esc_html( get_the_time( $feed ? 'M j · g:i a' : 'g:i a', $story ) ); ?></time><?php } ?>
				</p>
				<h3><?php echo esc_html( get_the_title( $story ) ); ?></h3>
				<?php if ( $deck && in_array( $style, array( 'lead', 'movie-feature', 'tv-feature', 'recap', 'watch-feature', 'interview-feature', 'desk', 'feed' ), true ) ) { ?><p class="cs-deck"><?php echo esc_html( $deck ); ?></p><?php } ?>
				<?php if ( $byline && in_array( $style, array( 'lead', 'movie-feature', 'tv-feature', 'interview-feature' ), true ) ) { ?><p class="cs-byline">By <?php echo esc_html( $byline ); ?></p><?php } ?>
			</div>
		</a>
	</article>
	<?php
}

function msc_cs_preview_desk( string $id, string $title, array $stories ): void {
	if ( ! $stories ) { return; }
	?>
	<section id="<?php echo esc_attr( $id ); ?>" class="cs-desk cs-desk--<?php echo esc_attr( $id ); ?>" aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>">
		<h2 id="<?php echo esc_attr( $id . '-title' ); ?>" class="cs-desk-title"><?php echo esc_html( $title ); ?></h2>
		<div class="cs-desk-grid">
			<?php foreach ( $stories as $index => $story ) {
				$style = 'interviews-features' === $id ? ( 0 === $index ? 'interview-feature' : 'desk-brief' ) : 'desk';
				msc_cs_preview_card( $story, $style );
			} ?>
		</div>
	</section>
	<?php
}
