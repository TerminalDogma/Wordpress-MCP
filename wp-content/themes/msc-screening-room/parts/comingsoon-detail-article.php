<?php
$story = $args['story'];
$byline = msc_cs_preview_meta( $story, 'byline' ) ?: get_the_author();
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
	<div class="cs-detail-story-copy"><?php the_content(); ?></div>
</article>
