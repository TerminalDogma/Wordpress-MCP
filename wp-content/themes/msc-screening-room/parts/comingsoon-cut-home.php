<?php
$source = get_page_by_path( 'homepage-preview' );
$stories = $source && 'publish' === $source->post_status && ! $source->post_password && 'home' === msc_cs_preview_meta( $source, 'preview' ) ? msc_cs_preview_stories( $source->ID ) : array();
$sections = array();
foreach ( $stories as $story ) { $sections[ msc_cs_preview_meta( $story, 'section' ) ][] = $story; }

$lead = msc_studio_previews( $args['ids'], 'article', 'cut' );
$movie_page = msc_studio_previews( $args['ids'], 'movie-page', 'cut' );
$headlines = array_slice( $sections['latest'] ?? array(), 0, 4 );
$movies = array_filter( $sections['criticism'] ?? array(), static fn( WP_Post $story ) => 'TV Review' !== msc_cs_preview_meta( $story, 'label' ) );
$movies = array_slice( array_merge( $movies, $sections['trailers'] ?? array() ), 0, 3 );
$tv = $sections['spotlight'][0] ?? null;
$recaps = array_slice( $sections['recaps'] ?? array(), 0, 3 );
$watch = array_filter( array_merge( $sections['watch'] ?? array(), $sections['feed'] ?? array() ), static fn( WP_Post $story ) => 'streaming' === msc_cs_preview_format( $story ) );
$watch = array_slice( $watch, 0, 3 );
$features = array_slice( $sections['features'] ?? array(), 0, 3 );
$feed = $lead ? array_merge( array( $lead ), $stories ) : $stories;
$calendar_url = \MSC\NetworkSites::calendar_url();
$art = msc_studio_art( 'cut' );

$stops = array( 'top-stories' => array( 'label' => 'Top Stories', 'image' => $art ) );
if ( $movies ) { $stops['movies'] = array( 'label' => 'Movies', 'story' => $movies[0] ); }
if ( $tv || $recaps ) { $stops['television'] = array( 'label' => 'TV & Recaps', 'story' => $tv ?: $recaps[0] ); }
if ( $watch ) { $stops['what-to-watch'] = array( 'label' => 'What to Watch', 'story' => $watch[0] ); }
if ( $features ) { $stops['features'] = array( 'label' => 'Interviews & Features', 'story' => $features[0] ); }
if ( $feed ) { $stops['the-feed'] = array( 'label' => 'Latest', 'story' => $headlines[0] ?? null, 'image' => $art ); }
get_template_part( 'parts/comingsoon-cut-filmstrip', null, array( 'stops' => $stops, 'label' => 'Sections' ) );
?>
<section id="top-stories" class="cut-top-stories" aria-labelledby="cut-top-title">
	<h2 id="cut-top-title" class="screen-reader-text">Top Stories</h2>
	<?php if ( $lead ) { ?>
		<article id="view-cut" class="cut-lead">
			<a href="<?php echo esc_url( get_permalink( $lead ) ); ?>">
				<img src="<?php echo esc_url( $art ); ?>" alt="" width="1440" height="960" fetchpriority="high" decoding="async">
				<div class="cut-lead-copy">
					<p class="cut-tag">Movie News</p>
					<h3><?php echo esc_html( get_the_title( $lead ) ); ?></h3>
					<?php if ( $lead->post_excerpt ) { ?><p class="cut-lead-deck"><?php echo esc_html( $lead->post_excerpt ); ?></p><?php } ?>
					<span class="cut-link">Read the story <span aria-hidden="true">↗</span></span>
				</div>
			</a>
		</article>
	<?php } ?>
	<?php if ( $headlines ) { ?>
		<aside class="cut-headlines" aria-labelledby="cut-headlines-title">
			<h3 id="cut-headlines-title">Latest Headlines</h3>
			<?php foreach ( $headlines as $story ) { msc_cs_preview_card( $story, 'brief' ); } ?>
			<a href="#the-feed" class="cut-link">All latest stories <span aria-hidden="true">↓</span></a>
		</aside>
	<?php } ?>
</section>

<?php if ( $movies ) { ?>
	<section id="movies" class="cut-desk cut-movies" aria-labelledby="cut-movies-title">
		<header class="cut-desk-header">
			<h2 id="cut-movies-title">Movies</h2>
			<?php if ( $movie_page ) { ?><a id="view-iron-man" class="cut-link" href="<?php echo esc_url( get_permalink( $movie_page ) ); ?>">Explore Iron Man <span aria-hidden="true">↗</span></a><?php } ?>
		</header>
		<div class="cut-movie-grid">
			<?php foreach ( $movies as $story ) { msc_cs_preview_card( $story, 'grid-review' ); } ?>
		</div>
	</section>
<?php } ?>

<?php if ( $tv || $recaps ) { ?>
	<section id="television" class="cut-desk cut-tv" aria-labelledby="cut-tv-title">
		<header class="cut-desk-header">
			<h2 id="cut-tv-title">TV &amp; Recaps</h2>
			<a class="cut-link" href="<?php echo esc_url( get_post_type_archive_link( 'msc_show' ) ); ?>">TV catalog <span aria-hidden="true">↗</span></a>
		</header>
		<div class="cut-tv-grid">
			<?php if ( $tv ) { msc_cs_preview_card( $tv, 'grid-review' ); } ?>
			<?php if ( $recaps ) { ?><div class="cut-recaps">
				<?php foreach ( $recaps as $story ) { msc_cs_preview_card( $story, 'recap-item' ); } ?>
			</div><?php } ?>
		</div>
	</section>
<?php } ?>

<section id="what-to-watch" class="cut-desk cut-watch" aria-labelledby="cut-watch-title">
	<header class="cut-desk-header">
		<h2 id="cut-watch-title">What to Watch</h2>
		<a id="view-calendar" class="cut-link" href="<?php echo esc_url( $calendar_url ); ?>">Release dates <span aria-hidden="true">↗</span></a>
	</header>
	<?php if ( $watch ) { ?><div class="cut-watch-grid">
		<?php foreach ( $watch as $story ) { msc_cs_preview_card( $story, 'grid-guide' ); } ?>
	</div><?php } ?>
</section>

<?php if ( $features ) { ?>
	<section id="features" class="cut-desk cut-features" aria-labelledby="cut-features-title">
		<header class="cut-desk-header"><h2 id="cut-features-title">Interviews &amp; Features</h2></header>
		<div class="cut-feature-grid">
			<?php foreach ( $features as $story ) { msc_cs_preview_card( $story, 'interview-feature' ); } ?>
		</div>
	</section>
<?php } ?>

<?php if ( $feed ) {
	get_template_part( 'parts/comingsoon-feed', null, array( 'stories' => $feed, 'url' => get_permalink( $args['post'] ), 'title' => 'Latest Stories' ) );
} ?>
