<?php
$story = $args['story'];
$byline = msc_cs_preview_meta( $story, 'byline' ) ?: get_the_author();
$research = $args['research'];
$related = array();
if ( $research ) {
	foreach ( array( 'last-meridian', 'meridian-effects' ) as $slug ) {
		$page = get_page_by_path( 'homepage-preview/' . $slug );
		if ( $page && 'publish' === $page->post_status && ! $page->post_password && 'story' === msc_cs_preview_meta( $page, 'preview' ) ) { $related[] = $page; }
	}
}
?>
<article class="cs-detail-story">
	<div class="cs-detail-story-lead">
		<header class="cs-detail-story-header">
			<a class="cs-label" href="<?php echo esc_url( msc_news_category_url( 'Movies' ) ); ?>">Movie News</a>
			<h1><?php the_title(); ?></h1>
			<?php if ( $story->post_excerpt ) { ?><p class="cs-deck"><?php echo esc_html( $story->post_excerpt ); ?></p><?php } ?>
			<p class="cs-byline">By <?php echo esc_html( $byline ); ?> <span aria-hidden="true">·</span> <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time></p>
		</header>
		<?php msc_cs_preview_art( $story, true ); ?>
	</div>
	<?php if ( $research ) { ?><div class="cs-research-reading-layout"><?php } ?>
		<div class="cs-detail-story-copy"><?php the_content(); ?></div>
		<?php if ( $research && ( $related || $args['home_id'] ) ) { ?>
			<aside class="cs-research-article-rail" aria-labelledby="research-article-rail-title">
				<h2 id="research-article-rail-title">Related Coverage</h2>
				<?php foreach ( $related as $page ) { msc_cs_preview_card( $page, 'brief' ); } ?>
				<?php if ( $args['home_id'] ) { ?><a class="cs-research-more" href="<?php echo esc_url( get_permalink( $args['home_id'] ) . '#upcoming-releases' ); ?>">Upcoming movie and TV releases →</a><?php } ?>
			</aside>
		<?php } ?>
	<?php if ( $research ) { ?></div><?php } ?>
</article>
