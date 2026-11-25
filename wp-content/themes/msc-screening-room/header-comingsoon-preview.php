<?php
$preview_parent = (int) wp_get_post_parent_id( get_queried_object_id() ) ?: get_queried_object_id();
$preview_url = get_permalink( $preview_parent );
$violet = 'violet' === get_post_meta( $preview_parent, '_msc_comingsoon_palette', true );
$movies_url = ( $args['movies'] ?? (bool) wp_get_post_parent_id( get_queried_object_id() ) ) ? $preview_url . '#movies' : msc_news_category_url( 'Movies' );
$tv_url = ( $args['tv'] ?? (bool) wp_get_post_parent_id( get_queried_object_id() ) ) ? $preview_url . '#tv' : msc_news_category_url( 'TV' );
add_filter( 'wp_robots', static function ( array $robots ): array {
	unset( $robots['index'] );
	$robots['noindex'] = true;
	return $robots;
}, 100 );
wp_enqueue_style( 'cs-editorial-preview', get_template_directory_uri() . '/assets/comingsoon/preview.css', array( 'network-presentation' ), '1.3.0' );
if ( $violet ) {
	wp_enqueue_style( 'cs-editorial-violet', get_template_directory_uri() . '/assets/comingsoon/preview-violet.css', array( 'cs-editorial-preview' ), '1.0.0' );
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( $violet ? 'cs-editorial cs-editorial--violet' : 'cs-editorial' ); ?> data-identity="a">
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="cs-masthead">
	<div class="cs-utility">
		<div class="cs-frame">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">← ComingSoon home</a>
			<span><?php echo $violet ? 'Homepage preview B' : 'Homepage preview'; ?></span>
		</div>
	</div>
	<div class="cs-frame cs-brand-row">
		<a class="cs-brand" href="<?php echo esc_url( $preview_url ); ?>" aria-label="ComingSoon homepage preview">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/network/logo-a.svg' ); ?>" width="260" height="73" alt="ComingSoon">
		</a>
		<a class="cs-calendar-link" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Release Calendar <span aria-hidden="true">→</span></a>
	</div>
	<div class="cs-nav-wrap">
		<div class="cs-frame cs-nav-row">
			<nav aria-label="Preview navigation" class="cs-nav">
				<a href="<?php echo esc_url( $preview_url . '#latest' ); ?>">Latest</a>
				<a href="<?php echo esc_url( $movies_url ); ?>">Movies</a>
				<a href="<?php echo esc_url( $tv_url ); ?>">TV</a>
				<a href="<?php echo esc_url( $preview_url . '#the-feed' ); ?>">The Feed</a>
				<a href="<?php echo esc_url( $preview_url . '#what-to-watch' ); ?>">What to Watch</a>
				<?php if ( \MSC\Reviews::enabled() ) { ?><a href="<?php echo esc_url( get_post_type_archive_link( 'msc_review' ) ); ?>">Reviews</a><?php } ?>
			</nav>
			<form class="cs-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
				<label class="screen-reader-text" for="cs-preview-search">Search ComingSoon</label>
				<input id="cs-preview-search" type="search" name="s" placeholder="Search" required>
				<button type="submit">Go</button>
			</form>
		</div>
	</div>
</header>
