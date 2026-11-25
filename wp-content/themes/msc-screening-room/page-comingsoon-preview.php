<?php
/** Template Name: ComingSoon Homepage Preview */
require_once __DIR__ . '/inc/comingsoon-preview.php';

$preview_page = get_queried_object_id();
$preview_c = 'c' === get_post_meta( $preview_page, '_msc_comingsoon_palette', true );
$story_source = (int) get_post_meta( $preview_page, '_msc_comingsoon_story_source', true );
if ( ! $story_source || 'home' !== get_post_meta( $story_source, '_msc_comingsoon_preview', true ) || 'publish' !== get_post_status( $story_source ) || post_password_required( $story_source ) ) {
	$story_source = $preview_page;
}
$stories = get_posts( array(
	'post_type' => 'page',
	'post_parent' => $story_source,
	'post_status' => 'publish',
	'has_password' => false,
	'posts_per_page' => -1,
	'meta_key' => '_msc_comingsoon_preview',
	'meta_value' => 'story',
	'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
) );
$sections = array();
foreach ( $stories as $story ) { $sections[ msc_cs_preview_meta( $story, 'section' ) ][] = $story; }
$lead = $sections['lead'][0] ?? null;
$spotlight = array_slice( $sections['spotlight'] ?? array(), 0, 2 );
$latest = $sections['latest'] ?? array();
$picks = $sections['watch'] ?? array();
$recaps = $sections['recaps'] ?? array();
$movie_reviews = array();
$tv_review = null;
foreach ( $sections['criticism'] ?? array() as $story ) {
	if ( 'TV Review' === msc_cs_preview_meta( $story, 'label' ) ) {
		$tv_review ??= $story;
	} else {
		$movie_reviews[] = $story;
	}
}

if ( $stories ) {
	wp_enqueue_script( 'cs-editorial-feed', get_template_directory_uri() . '/assets/comingsoon/feed.js', array(), '1.1.0', true );
}
get_header( 'comingsoon-preview', array(
	'movies' => (bool) $movie_reviews,
	'tv' => (bool) ( $tv_review || $recaps ),
) );
?>
<main id="main">
	<?php if ( $preview_c ) {
		get_template_part( 'parts/comingsoon-preview-c', null, compact( 'sections', 'lead', 'spotlight', 'latest', 'picks', 'movie_reviews', 'tv_review', 'recaps' ) );
	} else { ?>
	<div class="cs-frame">
		<h1 class="screen-reader-text">ComingSoon homepage preview</h1>
		<div class="cs-edition">
			<p><span aria-hidden="true"></span> Today on ComingSoon</p>
			<time datetime="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"><?php echo esc_html( wp_date( 'l, F j, Y' ) ); ?></time>
		</div>
		<?php if ( $lead || $spotlight || $latest ) { ?>
		<div class="cs-grid">
			<section class="cs-main-feature" aria-labelledby="cs-feature-title">
				<h2 id="cs-feature-title" class="screen-reader-text">Featured story</h2>
				<?php if ( $lead ) { msc_cs_preview_card( $lead, 'lead' ); } ?>
			</section>
			<section id="latest" class="cs-latest" aria-labelledby="cs-latest-title">
				<h2 id="cs-latest-title" class="cs-section-title">The Latest</h2>
				<?php foreach ( $latest as $story ) { msc_cs_preview_card( $story, 'brief' ); } ?>
			</section>
			<section class="cs-spotlight" aria-labelledby="cs-spotlight-title">
				<h2 id="cs-spotlight-title" class="cs-section-title">In Focus</h2>
				<?php foreach ( $spotlight as $story ) { msc_cs_preview_card( $story, 'spotlight' ); } ?>
			</section>
		</div>
		<?php } ?>
	</div>
	<?php
	get_template_part( 'parts/comingsoon-channels', null, compact( 'movie_reviews', 'tv_review', 'latest', 'recaps' ) );
	?>
	<?php if ( $picks ) { ?>
	<section id="what-to-watch" class="cs-watch" aria-labelledby="cs-watch-title">
		<div class="cs-frame">
			<div class="cs-watch-heading">
				<h2 id="cs-watch-title">What to Watch</h2>
				<a href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Browse upcoming releases <span aria-hidden="true">→</span></a>
			</div>
			<div class="cs-watch-layout">
				<?php msc_cs_preview_card( $picks[0], 'watch-feature' ); ?>
				<?php if ( count( $picks ) > 1 ) { ?>
					<div class="cs-watch-list"><?php foreach ( array_slice( $picks, 1 ) as $story ) { msc_cs_preview_card( $story, 'watch-item' ); } ?></div>
				<?php } ?>
			</div>
		</div>
	</section>
	<?php } ?>
	<?php if ( ! empty( $sections['features'] ) || ! empty( $sections['trailers'] ) ) { ?>
	<div class="cs-frame cs-desks">
		<?php
		msc_cs_preview_desk( 'interviews-features', 'Interviews & Features', $sections['features'] ?? array() );
		msc_cs_preview_desk( 'trailers-first-looks', 'Trailers & First Looks', $sections['trailers'] ?? array() );
		?>
	</div>
	<?php } ?>
	<?php } ?>
	<?php if ( $stories ) { get_template_part( 'parts/comingsoon-feed', null, array( 'stories' => $stories, 'url' => get_permalink( $preview_page ) ) ); } ?>
</main>
<?php get_footer( 'comingsoon-preview' ); ?>
