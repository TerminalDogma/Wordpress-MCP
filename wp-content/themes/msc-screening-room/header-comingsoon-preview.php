<?php
$preview_parent = $args['preview_home'] ?? ( (int) wp_get_post_parent_id( get_queried_object_id() ) ?: get_queried_object_id() );
$preview_url = $preview_parent ? get_permalink( $preview_parent ) : home_url( '/' );
$palette = $args['preview_variant'] ?? get_post_meta( $preview_parent, '_msc_comingsoon_palette', true );
$research = 'research' === $palette;
$preview_variant = match ( $palette ) {
	'b', 'violet' => 'b',
	'c', 'research' => 'c',
	default => '',
};
$show_home_sections = isset( $args['preview_home'] ) ? (bool) $preview_parent : (bool) wp_get_post_parent_id( get_queried_object_id() );
$movies_url = ( $args['movies'] ?? $show_home_sections ) ? $preview_url . '#movies' : msc_news_category_url( 'Movies' );
$tv_url = ( $args['tv'] ?? $show_home_sections ) ? $preview_url . '#tv' : msc_news_category_url( 'TV' );
add_filter( 'wp_robots', static function ( array $robots ): array {
	unset( $robots['index'] );
	$robots['noindex'] = true;
	return $robots;
}, 100 );
wp_enqueue_style( 'cs-editorial-preview', get_template_directory_uri() . '/assets/comingsoon/preview.css', array( 'network-presentation' ), '1.3.0' );
if ( 'b' === $preview_variant ) {
	wp_enqueue_style( 'cs-editorial-preview-b', get_template_directory_uri() . '/assets/comingsoon/preview-b.css', array( 'cs-editorial-preview' ), '1.1.0' );
}
if ( 'c' === $preview_variant ) {
	wp_enqueue_style( 'cs-editorial-preview-c', get_template_directory_uri() . '/assets/comingsoon/preview-c.css', array( 'cs-editorial-preview' ), '1.1.0' );
}
if ( $research ) {
	wp_enqueue_style( 'cs-research-preview', get_template_directory_uri() . '/assets/comingsoon/research-preview.css', array( 'cs-editorial-preview-c' ), '1.0.0' );
}
$body_classes = array( 'cs-editorial' );
if ( $preview_variant ) { $body_classes[] = 'cs-editorial--preview-' . $preview_variant; }
if ( $research ) { $body_classes[] = 'cs-editorial--research'; }
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( $body_classes ); ?> data-identity="a">
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="cs-masthead">
	<div class="cs-utility">
		<div class="cs-frame">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">← ComingSoon home</a>
			<?php if ( $research ) { ?>
				<nav class="cs-research-preview-links" aria-label="Research previews">
					<?php $research_ids = get_option( 'msc_comingsoon_detail_previews', array() );
					foreach ( array( 'Homepage' => $preview_parent, 'Article' => $research_ids['article-preview-research'] ?? 0, 'Movie page' => $research_ids['movie-page-preview-research'] ?? 0 ) as $label => $id ) {
						if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
						?><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"<?php if ( get_queried_object_id() === (int) $id ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $label ); ?></a><?php
					} ?>
				</nav>
			<?php } else { ?><span><?php echo esc_html( $args['preview_label'] ?? trim( 'Homepage preview ' . strtoupper( $preview_variant ) ) ); ?></span><?php } ?>
		</div>
	</div>
	<div class="cs-frame cs-brand-row">
		<a class="cs-brand" href="<?php echo esc_url( $preview_url ); ?>" aria-label="<?php echo $preview_parent ? 'ComingSoon homepage preview' : 'ComingSoon homepage'; ?>">
			<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/network/logo-a.svg' ); ?>" width="260" height="73" alt="ComingSoon">
		</a>
		<?php if ( 'c' !== $preview_variant ) { ?><a class="cs-calendar-link" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Release Calendar <span aria-hidden="true">→</span></a><?php } ?>
	</div>
	<div class="cs-nav-wrap">
		<div class="cs-frame cs-nav-row">
			<nav aria-label="Preview navigation" class="cs-nav">
				<a href="<?php echo esc_url( $preview_url . ( ! $preview_parent ? '' : ( 'c' === $preview_variant ? '#the-feed' : '#latest' ) ) ); ?>">Latest</a>
				<a href="<?php echo esc_url( $movies_url ); ?>">Movies</a>
				<a href="<?php echo esc_url( $tv_url ); ?>">TV</a>
				<?php if ( $preview_parent && 'c' !== $preview_variant ) { ?><a href="<?php echo esc_url( $preview_url . '#the-feed' ); ?>">The Feed</a><?php } ?>
				<a href="<?php echo esc_url( $preview_parent ? $preview_url . '#what-to-watch' : msc_news_category_url( 'Streaming' ) ); ?>">What to Watch</a>
				<?php if ( $research ) { ?>
					<a href="<?php echo esc_url( $preview_parent ? $preview_url . '#upcoming-releases' : \MSC\NetworkSites::calendar_url() ); ?>">Release Dates</a>
					<details class="cs-research-menu">
						<summary>Browse</summary>
						<div>
							<a href="<?php echo esc_url( get_post_type_archive_link( 'msc_movie' ) ); ?>">Movie catalog</a>
							<a href="<?php echo esc_url( get_post_type_archive_link( 'msc_show' ) ); ?>">TV catalog</a>
							<?php if ( \MSC\Reviews::enabled() ) { ?><a href="<?php echo esc_url( get_post_type_archive_link( 'msc_review' ) ); ?>">Reviews</a><?php } ?>
						</div>
					</details>
				<?php } else { ?>
					<?php if ( \MSC\Reviews::enabled() ) { ?><a href="<?php echo esc_url( get_post_type_archive_link( 'msc_review' ) ); ?>">Reviews</a><?php } ?>
					<?php if ( 'c' === $preview_variant ) { ?><a href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">Release Calendar</a><?php } ?>
				<?php } ?>
			</nav>
			<form class="cs-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
				<label class="screen-reader-text" for="cs-preview-search">Search ComingSoon</label>
				<input id="cs-preview-search" type="search" name="s" placeholder="Search" required>
				<button type="submit">Go</button>
			</form>
		</div>
	</div>
</header>
