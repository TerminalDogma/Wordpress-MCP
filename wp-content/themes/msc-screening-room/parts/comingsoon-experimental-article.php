<?php
$story = $args['story'];
$variant = $args['variant'];
$byline = msc_cs_preview_meta( $story, 'byline' ) ?: get_the_author();
$related_slugs = array(
	'signal' => array( 'last-meridian', 'meridian-effects' ),
	'zine' => array( 'celeste-rowe', 'road-comedy' ),
	'disc' => array( 'second-signal-trailer', 'switchboard-sound-design' ),
);
$related = array();
foreach ( $related_slugs[ $variant ] as $slug ) {
	$page = get_page_by_path( 'homepage-preview/' . $slug );
	if ( $page && 'publish' === $page->post_status && ! $page->post_password && 'story' === msc_cs_preview_meta( $page, 'preview' ) ) { $related[] = $page; }
}
?>
<article class="cs-exp-article">
	<div class="cs-exp-article-lead">
		<header class="cs-exp-article-head">
			<p class="cs-exp-label">Movie News</p>
			<h1><?php the_title(); ?></h1>
			<?php if ( $story->post_excerpt ) { ?><p class="cs-exp-deck"><?php echo esc_html( $story->post_excerpt ); ?></p><?php } ?>
			<p class="cs-exp-byline">By <?php echo esc_html( $byline ); ?> <span aria-hidden="true">/</span> <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time></p>
		</header>
		<div class="cs-exp-article-art"><?php msc_cs_preview_art( $story, true ); ?></div>
	</div>
	<div class="cs-exp-article-body<?php echo $related ? ' has-related' : ''; ?>">
		<div class="cs-exp-article-copy"><?php the_content(); ?></div>
		<?php if ( $related ) { ?>
			<aside aria-labelledby="exp-article-more" class="cs-exp-article-related">
				<h2 id="exp-article-more">Related Stories</h2>
				<?php foreach ( $related as $page ) { msc_cs_preview_card( $page, 'grid-highlight' ); } ?>
			</aside>
		<?php } ?>
	</div>
</article>
