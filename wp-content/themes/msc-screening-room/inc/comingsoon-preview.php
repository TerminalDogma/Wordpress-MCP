<?php
/** Shared rendering for the ComingSoon preview and its sample story pages. */
function msc_cs_preview_meta( WP_Post $post, string $key ): string {
	return (string) get_post_meta( $post->ID, '_msc_comingsoon_' . $key, true );
}

function msc_cs_preview_art( WP_Post $story, bool $priority = false ): void {
	if ( has_post_thumbnail( $story ) ) {
		echo get_the_post_thumbnail( $story, 'large', array( 'class' => 'cs-art', 'alt' => '', 'loading' => $priority ? 'eager' : 'lazy' ) );
		return;
	}
	$art = msc_cs_preview_meta( $story, 'art' );
	if ( ! in_array( $art, array( 'meridian', 'harbor', 'interview', 'diner', 'apartment', 'signal', 'station' ), true ) ) { return; }
	$base = get_template_directory_uri() . '/assets/comingsoon/';
	$attrs = ' width="508" height="508"';
	if ( 'meridian' === $art ) {
		$attrs = ' width="1440" height="960" srcset="' . esc_attr( $base . 'meridian-small.webp 760w, ' . $base . 'meridian.webp 1440w' ) . '" sizes="(max-width: 700px) 94vw, 680px"';
	}
	echo '<img class="cs-art cs-art--' . esc_attr( $art ) . '" src="' . esc_url( $base . $art . '.webp' ) . '" alt="" decoding="async" ' . ( $priority ? 'fetchpriority="high"' : 'loading="lazy"' ) . $attrs . '>';
}

function msc_cs_preview_card( WP_Post $story, string $style ): void {
	$deck = get_post_field( 'post_excerpt', $story );
	$byline = msc_cs_preview_meta( $story, 'byline' );
	?>
	<article class="cs-card cs-card--<?php echo esc_attr( $style ); ?>">
		<a class="cs-card-link" href="<?php echo esc_url( get_permalink( $story ) ); ?>">
			<?php if ( 'brief' !== $style ) { msc_cs_preview_art( $story, 'lead' === $style ); } ?>
			<div class="cs-card-copy">
				<p class="cs-label"><?php echo esc_html( msc_cs_preview_meta( $story, 'label' ) ); ?>
					<?php if ( 'brief' === $style ) { ?><time datetime="<?php echo esc_attr( get_post_time( DATE_W3C, true, $story ) ); ?>"><?php echo esc_html( get_the_time( 'g:i a', $story ) ); ?></time><?php } ?>
				</p>
				<h3><?php echo esc_html( get_the_title( $story ) ); ?></h3>
				<?php if ( 'lead' === $style || 'pick' === $style ) { ?><p class="cs-deck"><?php echo esc_html( $deck ); ?></p><?php } ?>
			</div>
		</a>
		<?php if ( 'lead' === $style && $byline ) { ?><p class="cs-byline">By <?php echo esc_html( $byline ); ?></p><?php } ?>
	</article>
	<?php
}
