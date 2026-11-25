<?php
$designs = msc_cs_experimental_designs();
$variant = $args['preview_variant'];
$kind = $args['preview_kind'];
$detail_ids = get_option( 'msc_comingsoon_detail_previews', array() );
$url = static function ( string $type, string $design ) use ( $detail_ids ): string {
	if ( 'homepage' === $type ) {
		$page = get_page_by_path( 'homepage-preview-' . $design );
		if ( ! $page || 'home' !== msc_cs_preview_meta( $page, 'preview' ) ) { return ''; }
		$id = $page->ID;
	} else {
		$id = $detail_ids[ $type . '-preview-' . $design ] ?? 0;
	}
	return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
};
$home = $url( 'homepage', $variant );
$assets = get_template_directory_uri() . '/assets/comingsoon/';
$deps = array( 'cs-editorial-preview' );
wp_enqueue_style( 'cs-editorial-preview', $assets . 'preview.css', array( 'network-presentation' ), '1.3.0' );
if ( 'homepage' !== $kind ) { $deps[] = 'cs-detail-preview'; }
wp_enqueue_style( 'cs-experimental', $assets . 'experimental.css', $deps, '1.0.0' );
wp_enqueue_style( 'cs-experimental-' . $variant, $assets . 'experimental-' . $variant . '.css', array( 'cs-experimental' ), '1.0.0' );
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
<body <?php body_class( array( 'cs-editorial', 'cs-experimental', 'cs-experimental--' . $variant ) ); ?> data-identity="a">
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="cs-exp-masthead">
	<div class="cs-exp-switchbar">
		<div class="cs-frame">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">← ComingSoon home</a>
			<nav aria-label="Compare experimental designs">
				<?php foreach ( $designs as $key => $name ) {
					$link = $url( $kind, $key );
					if ( ! $link ) { continue; }
					?><a href="<?php echo esc_url( $link ); ?>"<?php if ( $variant === $key ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $name ); ?></a><?php
				} ?>
			</nav>
		</div>
	</div>
	<div class="cs-frame cs-exp-navbar">
		<a class="cs-exp-brand" href="<?php echo esc_url( $home ?: home_url( '/' ) ); ?>" aria-label="ComingSoon homepage">Coming<span>Soon</span><small>.net</small></a>
		<nav aria-label="Experimental preview pages" class="cs-exp-nav">
			<?php foreach ( array( 'homepage' => 'Homepage', 'movie-page' => 'Iron Man', 'article' => 'News Article' ) as $type => $name ) {
				$link = $url( $type, $variant );
				if ( ! $link ) { continue; }
				?><a href="<?php echo esc_url( $link ); ?>"<?php if ( $kind === $type ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $name ); ?></a><?php
			} ?>
			<a href="<?php echo esc_url( ( $home ?: home_url( '/' ) ) . '#the-feed' ); ?>">Latest</a>
		</nav>
	</div>
</header>
