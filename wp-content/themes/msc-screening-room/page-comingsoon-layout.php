<?php
/** Template Name: ComingSoon Layout Exploration */
require_once __DIR__ . '/inc/comingsoon-preview.php';

$layouts = array( 'runway' => 'Release Runway', 'sheet' => 'Contact Sheet', 'index' => 'Cross-Reference' );
$id = get_queried_object_id();
$layout = get_post_meta( $id, '_msc_comingsoon_palette', true );
if ( ! is_string( $layout ) || ! isset( $layouts[ $layout ] ) ) { require __DIR__ . '/page.php'; return; }
$source = (int) get_post_meta( $id, '_msc_comingsoon_story_source', true );
$stories = array();
if ( ! post_password_required( $id ) && $source && 'publish' === get_post_status( $source ) && ! get_post_field( 'post_password', $source ) && 'home' === get_post_meta( $source, '_msc_comingsoon_preview', true ) ) {
	$stories = msc_cs_preview_stories( $source );
}

$assets = get_template_directory_uri() . '/assets/comingsoon/';
wp_enqueue_style( 'cs-layout', $assets . 'layouts.css', array( 'network-presentation' ), '1.0.0' );
wp_enqueue_style( 'cs-layout-' . $layout, $assets . 'layout-' . $layout . '.css', array( 'cs-layout' ), '1.0.0' );
if ( 'sheet' === $layout ) {
	wp_enqueue_script( 'cs-editorial-feed', $assets . 'feed.js', array(), '1.1.0', true );
}
add_filter( 'wp_robots', static function ( array $robots ): array {
	unset( $robots['index'] );
	$robots['noindex'] = true;
	return $robots;
}, 100 );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( array( 'cs-layout', 'cs-layout--' . $layout ) ); ?> data-identity="a">
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="layout-top">
	<div class="layout-width layout-top-inner">
		<a class="layout-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="ComingSoon home">Coming<span>Soon</span></a>
		<nav aria-label="Compare layouts">
			<?php foreach ( $layouts as $key => $label ) {
				$page = get_page_by_path( 'homepage-preview-' . $key );
				if ( ! $page || 'publish' !== $page->post_status || $page->post_password || $key !== msc_cs_preview_meta( $page, 'palette' ) ) { continue; }
				?><a href="<?php echo esc_url( get_permalink( $page ) ); ?>"<?php if ( $key === $layout ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $label ); ?></a><?php
			} ?>
		</nav>
	</div>
</header>
<main id="main" class="layout-width">
	<?php if ( post_password_required( $id ) ) {
		echo get_the_password_form( $id );
	} else {
		get_template_part( 'parts/comingsoon-layout-' . $layout, null, array( 'stories' => $stories, 'url' => get_permalink( $id ), 'title' => $layouts[ $layout ] ) );
	} ?>
</main>
<footer class="layout-footer">
	<div class="layout-width layout-footer-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">ComingSoon home ↗</a>
		<p>Sample editorial stories are fictional.</p>
		<div id="msc-source-footer"><?php echo \MSC\Attribution::footer(); ?></div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
