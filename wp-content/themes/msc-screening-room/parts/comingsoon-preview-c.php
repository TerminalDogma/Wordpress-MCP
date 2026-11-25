<?php
$sections = $args['sections'];
$lead = $args['lead'];
$latest = $args['latest'];
$watch = $args['picks'];
$movies = $args['movie_reviews'];
$tv = $args['tv_review'];
$recaps = $args['recaps'];
$featured = array_slice( array_merge( $args['spotlight'], $sections['trailers'] ?? array() ), 0, 3 );
$editors_picks = array_slice( array_merge( $sections['features'] ?? array(), array_slice( $movies, 1 ) ), 0, 4 );
$movie_news = array_filter( $latest, static fn( WP_Post $story ) => 'Movie News' === msc_cs_preview_meta( $story, 'label' ) );
?>
<div class="cs-frame cs-grid-page">
	<h1 class="screen-reader-text">ComingSoon homepage preview C</h1>

	<?php if ( $lead || $featured ) { ?>
	<section class="cs-cover-grid" aria-label="Featured stories">
		<?php if ( $lead ) { msc_cs_preview_card( $lead, 'grid-lead' ); } ?>
		<?php if ( $featured ) { ?>
			<div class="cs-cover-secondary">
				<?php foreach ( $featured as $story ) { msc_cs_preview_card( $story, 'grid-cover' ); } ?>
			</div>
		<?php } ?>
	</section>
	<?php } ?>

	<?php if ( $editors_picks ) { ?>
	<section class="cs-grid-picks" aria-labelledby="cs-grid-picks-title">
		<h2 class="cs-grid-title" id="cs-grid-picks-title">Editors’ Picks</h2>
		<div class="cs-picks-grid">
			<?php foreach ( $editors_picks as $story ) { msc_cs_preview_card( $story, 'grid-brief' ); } ?>
		</div>
	</section>
	<?php } ?>

	<?php if ( $latest || $watch ) { ?>
	<div class="cs-grid-discovery">
		<?php if ( $latest ) { ?>
			<section id="latest" aria-labelledby="cs-grid-latest-title">
				<div class="cs-grid-heading">
					<h2 class="cs-grid-title" id="cs-grid-latest-title">Latest</h2>
					<a href="#the-feed">See all stories <span aria-hidden="true">→</span></a>
				</div>
				<div class="cs-grid-news-list">
					<?php foreach ( $latest as $story ) { msc_cs_preview_card( $story, 'grid-news' ); } ?>
				</div>
			</section>
		<?php } ?>

		<?php if ( $watch ) { ?>
			<aside id="what-to-watch" class="cs-grid-watch" aria-labelledby="cs-grid-watch-title">
				<h2 class="cs-grid-title" id="cs-grid-watch-title">What to Watch</h2>
				<?php
				msc_cs_preview_card( $watch[0], 'grid-guide' );
				foreach ( array_slice( $watch, 1 ) as $story ) { msc_cs_preview_card( $story, 'grid-brief' ); }
				?>
				<a class="cs-guide-calendar" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Upcoming releases <span aria-hidden="true">→</span></a>
			</aside>
		<?php } ?>
	</div>
	<?php } ?>

	<?php if ( $movies || $tv || $recaps ) { ?>
	<div class="cs-grid-channels">
		<?php if ( $movies ) { ?>
		<section id="movies" class="cs-grid-channel" aria-labelledby="cs-grid-movies-title">
			<div class="cs-grid-heading">
				<h2 class="cs-grid-title" id="cs-grid-movies-title">Movies</h2>
				<a href="<?php echo esc_url( msc_news_category_url( 'Movies' ) ); ?>">Movie news <span aria-hidden="true">→</span></a>
			</div>
			<?php
			msc_cs_preview_card( $movies[0], 'grid-review' );
			foreach ( $movie_news as $story ) { msc_cs_preview_card( $story, 'grid-brief' ); }
			?>
		</section>
		<?php } ?>

		<?php if ( $tv || $recaps ) { ?>
		<section id="tv" class="cs-grid-channel cs-grid-channel--tv" aria-labelledby="cs-grid-tv-title">
			<div class="cs-grid-heading">
				<h2 class="cs-grid-title" id="cs-grid-tv-title">TV</h2>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'msc_show' ) ); ?>">TV catalog <span aria-hidden="true">→</span></a>
			</div>
			<?php if ( $tv ) { msc_cs_preview_card( $tv, 'grid-review' ); } ?>
			<?php if ( $recaps ) { ?>
			<div id="tv-recaps" class="cs-grid-recaps">
				<h3>TV Recaps</h3>
				<?php foreach ( $recaps as $story ) { msc_cs_preview_card( $story, 'grid-brief' ); } ?>
			</div>
			<?php } ?>
		</section>
		<?php } ?>
	</div>
	<?php } ?>
</div>
