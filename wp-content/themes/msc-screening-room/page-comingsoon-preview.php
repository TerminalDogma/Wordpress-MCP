<?php
/** Template Name: ComingSoon Homepage Preview */
require_once __DIR__ . '/inc/comingsoon-preview.php';

$stories = get_posts( array(
	'post_type' => 'page',
	'post_parent' => get_queried_object_id(),
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

if ( $stories ) {
	wp_enqueue_script( 'cs-editorial-feed', get_template_directory_uri() . '/assets/comingsoon/feed.js', array(), '1.1.0', true );
}
get_header( 'comingsoon-preview' );
?>
<main id="main">
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
	<?php if ( $picks ) { ?>
	<section id="what-to-watch" class="cs-watch" aria-labelledby="cs-watch-title">
		<div class="cs-frame">
			<div class="cs-watch-heading">
				<h2 id="cs-watch-title">What to Watch</h2>
				<a href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Browse upcoming releases <span aria-hidden="true">→</span></a>
			</div>
			<div class="cs-watch-grid<?php echo count( $picks ) >= 4 ? ' cs-watch-grid--four' : ''; ?>"><?php foreach ( $picks as $story ) { msc_cs_preview_card( $story, 'pick' ); } ?></div>
		</div>
	</section>
	<?php } ?>
	<?php if ( array_intersect( array_keys( $sections ), array( 'recaps', 'criticism', 'features', 'trailers' ) ) ) { ?>
	<div class="cs-frame cs-desks">
		<?php if ( ! empty( $sections['recaps'] ) || ! empty( $sections['criticism'] ) ) { ?>
		<div class="cs-desks-split">
			<?php
			msc_cs_preview_desk( 'tv-recaps', 'TV Recaps', $sections['recaps'] ?? array() );
			msc_cs_preview_desk( 'criticism', 'Reviews & Criticism', $sections['criticism'] ?? array() );
			?>
		</div>
		<?php } ?>
		<?php
		msc_cs_preview_desk( 'interviews-features', 'Interviews & Features', $sections['features'] ?? array() );
		msc_cs_preview_desk( 'trailers-first-looks', 'Trailers & First Looks', $sections['trailers'] ?? array() );
		?>
	</div>
	<?php } ?>
	<?php if ( $stories ) { get_template_part( 'parts/comingsoon-feed', null, array( 'stories' => $stories, 'url' => get_permalink( get_queried_object_id() ) ) ); } ?>
</main>
<?php get_footer( 'comingsoon-preview' ); ?>
