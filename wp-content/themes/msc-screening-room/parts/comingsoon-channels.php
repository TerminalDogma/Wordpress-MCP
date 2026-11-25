<?php
/** Two editorial sections with separate treatments for film and television. */
$movie_reviews = $args['movie_reviews'];
$tv_review = $args['tv_review'];
$recaps = $args['recaps'];
$movie_news = array_filter( $args['latest'], static fn( WP_Post $story ) => 'Movie News' === msc_cs_preview_meta( $story, 'label' ) );
$movie_sidebar = array_slice( array_merge( array_slice( $movie_reviews, 1 ), $movie_news ), 0, 3 );
?>

<?php if ( $movie_reviews ) { ?>
<section id="movies" class="cs-channel cs-channel--movies cs-frame" aria-labelledby="cs-movies-title">
	<header class="cs-channel-heading">
		<h2 id="cs-movies-title">Movies</h2>
		<a href="<?php echo esc_url( msc_news_category_url( 'Movies' ) ); ?>">Movie news <span aria-hidden="true">→</span></a>
	</header>
	<div class="cs-movies-layout">
		<?php msc_cs_preview_card( $movie_reviews[0], 'movie-feature' ); ?>
		<?php if ( $movie_sidebar ) { ?>
			<aside class="cs-movies-aside" aria-labelledby="cs-movies-aside-title">
				<h3 id="cs-movies-aside-title">More movie coverage</h3>
				<?php foreach ( $movie_sidebar as $index => $story ) {
					msc_cs_preview_card( $story, 0 === $index ? 'movie-essay' : 'movie-item' );
				} ?>
			</aside>
		<?php } ?>
	</div>
</section>
<?php } ?>

<?php if ( $tv_review || $recaps ) { ?>
<section id="tv" class="cs-channel cs-channel--tv" aria-labelledby="cs-tv-title">
	<div class="cs-frame">
		<header class="cs-channel-heading">
			<h2 id="cs-tv-title">TV</h2>
			<?php if ( $recaps ) { ?><a href="#tv-recaps">Browse recaps <span aria-hidden="true">↓</span></a><?php } ?>
		</header>
		<?php if ( $tv_review ) { msc_cs_preview_card( $tv_review, 'tv-feature' ); } ?>
		<?php if ( $recaps ) { ?>
			<section id="tv-recaps" class="cs-recaps" aria-labelledby="cs-recaps-title">
				<div class="cs-recaps-heading">
					<h3 id="cs-recaps-title">TV Recaps</h3>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'msc_show' ) ); ?>">Browse the TV catalog <span aria-hidden="true">→</span></a>
				</div>
				<div class="cs-recaps-layout">
					<?php msc_cs_preview_card( $recaps[0], 'recap-feature' ); ?>
					<?php if ( count( $recaps ) > 1 ) { ?>
						<div class="cs-recaps-list">
							<?php foreach ( array_slice( $recaps, 1 ) as $story ) { msc_cs_preview_card( $story, 'recap-item' ); } ?>
						</div>
					<?php } ?>
				</div>
			</section>
		<?php } ?>
	</div>
</section>
<?php } ?>
