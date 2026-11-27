<?php
$sections = $args['sections'];
$lead = $args['lead'];
$movies = $args['movie_reviews'];
$tv = $args['tv_review'];
$recaps = $args['recaps'];
$featured = array_slice( array_merge( $args['spotlight'], $sections['trailers'] ?? array() ), 0, 3 );
$highlights = array_slice( $args['latest'], 0, 5 );
$criticism = array_filter( $args['picks'], static fn( WP_Post $story ) => 'criticism' === msc_cs_preview_format( $story ) );
$criticism = array_slice( array_merge( $sections['criticism'] ?? array(), $criticism ), 0, 4 );
$watch = array_filter( array_merge( $args['picks'], $sections['feed'] ?? array() ), static fn( WP_Post $story ) => 'streaming' === msc_cs_preview_format( $story ) );
$movie_news = array_filter( $args['latest'], static fn( WP_Post $story ) => 'Movie News' === msc_cs_preview_meta( $story, 'label' ) );
?>
<div class="cs-frame cs-grid-page">
	<h1 class="screen-reader-text">ComingSoon homepage preview C</h1>

	<?php if ( $lead || $featured ) { ?>
	<section class="cs-cover-grid" aria-label="Featured stories">
		<?php if ( $lead ) { msc_cs_preview_card( $lead, 'grid-lead' ); } ?>
		<?php if ( $featured ) { ?>
			<div class="cs-cover-secondary">
				<?php foreach ( $featured as $index => $story ) { msc_cs_preview_card( $story, 2 === $index ? 'grid-split' : 'grid-cover' ); } ?>
			</div>
		<?php } ?>
	</section>
	<?php } ?>

	<?php if ( $highlights ) { ?>
	<section class="cs-grid-highlights" aria-labelledby="cs-highlights-title">
		<div class="cs-grid-heading">
			<h2 class="cs-grid-title" id="cs-highlights-title">News Highlights</h2>
			<a href="#the-feed">Latest stories <span aria-hidden="true">→</span></a>
		</div>
		<div class="cs-highlight-grid">
			<?php foreach ( $highlights as $story ) { msc_cs_preview_card( $story, 'grid-highlight' ); } ?>
		</div>
	</section>
	<?php } ?>

	<?php get_template_part( 'parts/comingsoon-releases', null, array( 'releases' => $args['releases'] ?? array() ) ); ?>

	<?php if ( $criticism ) { ?>
	<section class="cs-grid-criticism" aria-labelledby="cs-grid-criticism-title">
		<div class="cs-grid-heading">
			<h2 class="cs-grid-title" id="cs-grid-criticism-title">Reviews &amp; Criticism</h2>
		</div>
		<div class="cs-criticism-grid">
			<?php foreach ( $criticism as $story ) { msc_cs_preview_card( $story, 'grid-roundup' ); } ?>
		</div>
	</section>
	<?php } ?>

	<?php if ( $watch ) { ?>
	<section id="what-to-watch" class="cs-grid-watch" aria-labelledby="cs-grid-watch-title">
		<div class="cs-grid-heading">
			<h2 class="cs-grid-title" id="cs-grid-watch-title">What to Watch</h2>
			<a class="cs-guide-calendar" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Upcoming releases <span aria-hidden="true">→</span></a>
		</div>
		<div class="cs-watch-guides">
			<?php foreach ( $watch as $story ) { msc_cs_preview_card( $story, 'grid-guide' ); } ?>
		</div>
	</section>
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
