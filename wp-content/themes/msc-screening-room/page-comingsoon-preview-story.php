<?php
/** Template Name: ComingSoon Preview Story */
require_once __DIR__ . '/inc/comingsoon-preview.php';
get_header( 'comingsoon-preview' );
?>
<main id="main" class="cs-frame cs-story">
	<?php while ( have_posts() ) {
		the_post();
		$story = get_post();
		$parent = wp_get_post_parent_id( $story );
		?>
		<article>
			<header class="cs-story-header">
				<?php if ( $parent ) { ?><a class="cs-story-back" href="<?php echo esc_url( get_permalink( $parent ) ); ?>">← Back to the homepage preview</a><?php } ?>
				<p class="cs-label"><?php echo esc_html( msc_cs_preview_meta( $story, 'label' ) ); ?></p>
				<h1><?php the_title(); ?></h1>
				<p class="cs-deck"><?php echo esc_html( $story->post_excerpt ); ?></p>
				<p class="cs-byline">By <?php echo esc_html( msc_cs_preview_meta( $story, 'byline' ) ); ?> <span aria-hidden="true">·</span> <?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></p>
			</header>
			<?php msc_cs_preview_art( $story, true ); ?>
			<div class="cs-story-copy"><?php the_content(); ?></div>
		</article>
	<?php } ?>
</main>
<?php get_footer( 'comingsoon-preview' ); ?>
