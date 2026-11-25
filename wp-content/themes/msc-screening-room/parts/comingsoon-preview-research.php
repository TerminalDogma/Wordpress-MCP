<?php
use MSC\{NetworkSites, ReleaseCalendar};

$sections = $args['sections'];
$previews = get_option( 'msc_comingsoon_detail_previews', array() );
$lead = $args['lead'];
$support = array_slice( array_merge( $args['spotlight'], $sections['trailers'] ?? array() ), 0, 3 );
$latest = array_slice( $args['latest'], 0, 4 );
$watch = array_slice( array_values( array_filter( array_merge( $args['picks'], $sections['feed'] ?? array() ), static fn( WP_Post $story ) => 'streaming' === msc_cs_preview_format( $story ) ) ), 0, 3 );
$today = current_datetime();
$iso_today = $today->format( 'Y-m-d' );
$end = $today->modify( '+29 days' )->format( 'Y-m-d' );
$month = $today->modify( 'first day of this month' );
$events = array();
for ( ; $month->format( 'Y-m-d' ) <= $end && count( $events ) < 7; $month = $month->modify( '+1 month' ) ) {
	$month_events = ReleaseCalendar::data( $month->format( 'Y-m' ) )['events'];
	foreach ( $month_events as $event ) {
		if ( $event['date'] < $iso_today || $event['date'] > $end || ! in_array( $event['type'], array( 'msc_movie', 'msc_show', 'msc_episode' ), true ) ) { continue; }
		$events[] = $event;
	}
}
$events = array_slice( $events, 0, 7 );
?>
<div class="cs-frame cs-research-home">
	<h1 class="screen-reader-text">ComingSoon</h1>
	<nav class="cs-research-topics" aria-label="In focus">
		<span>In focus</span>
		<a href="#box-office">Weekend box office</a>
		<a href="#what-to-watch">Streaming picks</a>
		<a href="#tv">TV recaps</a>
		<?php if ( ! empty( $previews['movie-page-preview-research'] ) && 'publish' === get_post_status( $previews['movie-page-preview-research'] ) ) { ?>
			<a href="<?php echo esc_url( get_permalink( $previews['movie-page-preview-research'] ) ); ?>">Iron Man</a>
		<?php } ?>
	</nav>
	<div class="cs-research-hero">
		<section class="cs-research-featured" aria-label="Featured stories">
			<?php if ( $lead ) { msc_cs_preview_card( $lead, 'grid-lead' ); } ?>
			<?php if ( $support ) { ?>
				<div class="cs-research-support">
					<?php foreach ( $support as $story ) { msc_cs_preview_card( $story, 'grid-highlight' ); } ?>
				</div>
			<?php } ?>
		</section>
		<?php if ( $latest ) { ?>
			<section class="cs-research-latest" aria-labelledby="research-latest-title">
				<h2 id="research-latest-title">Latest News</h2>
				<?php foreach ( $latest as $story ) { msc_cs_preview_card( $story, 'brief' ); } ?>
				<a class="cs-research-more" href="#the-feed">All latest stories <span aria-hidden="true">→</span></a>
			</section>
		<?php } ?>
	</div>

	<div class="cs-research-service">
		<section id="upcoming-releases" class="cs-research-releases" aria-labelledby="research-release-title">
			<div class="cs-research-heading">
				<h2 id="research-release-title">Release Dates</h2>
				<a href="<?php echo esc_url( NetworkSites::calendar_url() ); ?>">Full schedule <span aria-hidden="true">→</span></a>
			</div>
			<?php if ( $events ) { ?>
				<ul class="cs-research-release-list">
				<?php foreach ( $events as $event ) {
					$day = new DateTimeImmutable( $event['date'], wp_timezone() );
					$title = $event['show_title'] ? $event['show_title'] . ': ' . $event['title'] : $event['title'];
					?>
					<li>
						<time datetime="<?php echo esc_attr( $event['date'] ); ?>"<?php if ( $iso_today === $event['date'] ) { ?> class="cs-research-today"<?php } ?>><?php echo esc_html( $iso_today === $event['date'] ? 'Today' : wp_date( 'M j', $day->getTimestamp() ) ); ?></time>
						<a href="<?php echo esc_url( $event['url'] ); ?>"><?php echo esc_html( $title ); ?></a>
						<span class="cs-research-release-platform"><?php echo esc_html( $event['kind'] ); ?>
							<?php foreach ( $event['services'] as $service ) { ?>
								<span><?php if ( $service['logo'] ) { ?><img src="<?php echo esc_url( $service['logo'] ); ?>" alt="" width="29" height="17" loading="lazy"><?php } ?><?php echo esc_html( $service['title'] ); ?></span>
							<?php } ?>
						</span>
					</li>
				<?php } ?>
				</ul>
			<?php } else { ?><p>No upcoming releases are listed in the catalog for the next 30 days.</p><?php } ?>
		</section>
		<section id="what-to-watch" class="cs-research-watch" aria-labelledby="research-watch-title">
			<div class="cs-research-heading"><h2 id="research-watch-title">What to Watch</h2></div>
			<?php foreach ( $watch as $story ) { msc_cs_preview_card( $story, 'watch-item' ); } ?>
			<?php if ( ! $watch ) { ?><a class="cs-research-more" href="<?php echo esc_url( msc_news_category_url( 'Streaming' ) ); ?>">Streaming coverage →</a><?php } ?>
		</section>
	</div>

	<section id="box-office" class="cs-research-box-office" aria-labelledby="research-box-office-title">
		<div>
			<p class="cs-label">Sample figures</p>
			<h2 id="research-box-office-title">Weekend Box Office</h2>
			<p>Domestic weekend grosses</p>
		</div>
		<ol>
			<?php foreach ( array( 'The Last Meridian' => '$24.8M', 'The Long Way Home' => '$11.2M', 'The Last Light' => '$7.6M' ) as $film => $gross ) { ?>
				<li><span><?php echo esc_html( $film ); ?></span><strong><?php echo esc_html( $gross ); ?></strong></li>
			<?php } ?>
		</ol>
	</section>

	<div class="cs-research-desks">
		<section id="movies" aria-labelledby="research-movies-title">
			<div class="cs-research-heading"><h2 id="research-movies-title">Movies</h2><a href="<?php echo esc_url( get_post_type_archive_link( 'msc_movie' ) ); ?>">Movie catalog →</a></div>
			<?php foreach ( array_slice( $args['movie_reviews'], 0, 2 ) as $story ) { msc_cs_preview_card( $story, 'grid-review' ); } ?>
		</section>
		<section id="tv" aria-labelledby="research-tv-title">
			<div class="cs-research-heading"><h2 id="research-tv-title">TV &amp; Recaps</h2><a href="<?php echo esc_url( get_post_type_archive_link( 'msc_show' ) ); ?>">TV catalog →</a></div>
			<?php if ( $args['tv_review'] ) { msc_cs_preview_card( $args['tv_review'], 'grid-review' ); } ?>
			<?php foreach ( $args['recaps'] as $story ) { msc_cs_preview_card( $story, 'grid-brief' ); } ?>
		</section>
	</div>

	<?php if ( ! empty( $sections['features'] ) ) { ?>
		<section class="cs-research-originals" aria-labelledby="research-originals-title">
			<div class="cs-research-heading"><h2 id="research-originals-title">Interviews &amp; Features</h2></div>
			<div class="cs-research-support"><?php foreach ( array_slice( $sections['features'], 0, 3 ) as $story ) { msc_cs_preview_card( $story, 'grid-highlight' ); } ?></div>
		</section>
	<?php } ?>
</div>
