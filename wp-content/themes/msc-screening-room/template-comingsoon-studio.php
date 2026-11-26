<?php
/**
 * Template Name: ComingSoon Studio Preview
 * Template Post Type: page, post
 */

use MSC\{Attribution, MovieEditorial};

require_once __DIR__ . '/inc/comingsoon-studio.php';

$post = get_queried_object();
$designs = msc_studio_designs();
$design = get_post_meta( $post->ID, '_msc_studio_design', true );
$kind = get_post_meta( $post->ID, '_msc_studio_kind', true );
$kinds = array( 'homepage' => 'Homepage', 'movie-page' => 'Iron Man', 'article' => 'Article' );
if ( ! isset( $designs[ $design ], $kinds[ $kind ] ) ) { require __DIR__ . '/page.php'; return; }
$cut_editorial = 'cut' === $design && 'movie-page' !== $kind;
$ids = get_option( 'msc_comingsoon_studio_previews', array() );
$password = post_password_required( $post );
$title = $designs[ $design ];
$deck = '';
$release = $facts = array();
$scenes = array();
if ( ! $password ) {
	if ( 'homepage' === $kind ) {
		if ( ! $cut_editorial ) { $scenes = msc_studio_home( $ids, $design ); }
	} elseif ( 'movie-page' === $kind ) {
		$movie = msc_studio_iron_man();
		$title = $movie['data']['title'] ?? 'Iron Man';
		if ( $movie ) {
			$data = $movie['data'];
			$release = MovieEditorial::theatrical_release( $data );
			$genres = array_map( static fn( $genre ) => msc_screen_genre( $genre, 'msc_movie' ), array_slice( (array) ( $data['genres'] ?? array() ), 0, 3 ) );
			$facts = array_filter( array( $data['content_rating'] ?? '', empty( $data['runtime'] ) ? '' : $data['runtime'] . ' min', ...$genres ) );
			$scenes = msc_studio_movie( $movie );
		}
	} else {
		$title = get_the_title( $post );
		$deck = $post->post_excerpt;
		if ( ! $cut_editorial ) { $scenes = msc_studio_article( $post, $design ); }
	}
}

$assets = get_template_directory_uri() . '/assets/studio/';
$dependencies = array( 'network-presentation' );
if ( 'movie-page' === $kind ) { $dependencies[] = 'msc-movies'; }
wp_enqueue_style( 'cs-studio', $assets . 'studio.css', $dependencies, '1.0.0' );
wp_enqueue_style( 'cs-studio-' . $design, $assets . $design . '.css', array( 'cs-studio' ), '1.0.0' );
if ( $cut_editorial ) {
	require_once __DIR__ . '/inc/comingsoon-preview.php';
	wp_enqueue_style( 'cs-cut-editorial', $assets . 'cut-editorial.css', array( 'cs-studio-cut' ), '1.0.0' );
	if ( 'homepage' === $kind ) {
		wp_enqueue_script( 'cs-editorial-feed', get_template_directory_uri() . '/assets/comingsoon/feed.js', array(), '1.1.0', true );
	}
} else {
	wp_enqueue_script( 'cs-studio', $assets . 'studio.js', array(), '1.0.0', true );
}
add_filter( 'wp_robots', static function ( array $robots ): array {
	unset( $robots['index'] );
	$robots['noindex'] = true;
	return $robots;
}, 100 );

$home = msc_studio_previews( $ids, 'homepage', $design );
$current_art = reset( $scenes )['image'] ?? '';
$area = match ( $design ) { 'orbit' => 'Story map', 'cut' => 'Filmstrip', 'tower' => 'Floors' };
$frame_marks = array( 'trailers' => '▶', 'watch' => '◉', 'scores' => '★', 'details' => '▤' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( array( 'cs-studio', 'cs-studio--' . $design ) ); ?> data-identity="a">
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="studio-top">
	<div class="studio-top-inner">
		<a class="studio-brand" href="<?php echo esc_url( $home ? get_permalink( $home ) : home_url( '/' ) ); ?>">Coming<span>Soon</span></a>
		<nav class="studio-types" aria-label="Preview pages">
			<?php foreach ( $kinds as $key => $name ) {
				$target = msc_studio_previews( $ids, $key, $design );
				if ( ! $target ) { continue; }
				?><a href="<?php echo esc_url( get_permalink( $target ) ); ?>"<?php if ( $kind === $key ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $name ); ?></a><?php
			} ?>
		</nav>
		<nav class="studio-designs" aria-label="Compare designs">
			<?php foreach ( $designs as $key => $name ) {
				$target = msc_studio_previews( $ids, $kind, $key );
				if ( ! $target ) { continue; }
				?><a href="<?php echo esc_url( get_permalink( $target ) ); ?>"<?php if ( $design === $key ) { ?> aria-current="page"<?php } ?>><?php echo esc_html( $name ); ?></a><?php
			} ?>
		</nav>
	</div>
</header>
<main id="main" class="studio-main<?php if ( $cut_editorial ) { ?> cut-editorial<?php } ?>" data-studio data-studio-kind="<?php echo esc_attr( $kind ); ?>">
	<header class="studio-title">
		<?php if ( 'homepage' !== $kind && ! $password ) { ?><p class="studio-kicker"><?php echo 'article' === $kind ? 'Movie News · Fictional Sample' : 'Movie Guide'; ?></p><?php } ?>
		<h1><?php echo esc_html( $password ? 'Protected Preview' : $title ); ?></h1>
		<?php if ( $deck ) { ?><p class="studio-deck"><?php echo esc_html( $deck ); ?></p><?php } ?>
		<?php if ( $cut_editorial && 'article' === $kind && ! $password ) { ?><p class="cut-byline"><strong>ComingSoon</strong> <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( 'F j, Y', $post ) ); ?></time></p><?php } ?>
		<?php if ( ! empty( $release['date'] ) || $facts ) { ?>
			<div class="studio-facts">
				<?php if ( ! empty( $release['date'] ) ) { ?><time datetime="<?php echo esc_attr( $release['date'] ); ?>"><?php echo esc_html( wp_date( 'F j, Y', strtotime( $release['date'] . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></time><?php } ?>
				<?php foreach ( $facts as $fact ) { ?><span><?php echo esc_html( $fact ); ?></span><?php } ?>
			</div>
		<?php } ?>
	</header>
	<?php if ( $password ) {
		echo get_the_password_form( $post );
	} elseif ( $cut_editorial ) {
		get_template_part( 'parts/comingsoon-cut-' . ( 'homepage' === $kind ? 'home' : 'article' ), null, array( 'ids' => $ids, 'post' => $post ) );
	} elseif ( ! $scenes ) { ?><p>Publish the Iron Man catalog entry to fill this preview.</p><?php } else { ?>
	<div class="studio-layout">
		<nav class="studio-index" aria-label="<?php echo esc_attr( $area ); ?>">
			<h2><?php echo esc_html( $area ); ?></h2>
			<?php if ( 'orbit' === $design ) { ?>
				<div class="studio-orbit-field" aria-hidden="true"><div class="studio-orbit-core"><img alt="" src="<?php echo esc_url( $current_art ?: msc_studio_art( $design ) ); ?>" data-studio-core data-studio-fallback="<?php echo esc_url( msc_studio_art( $design ) ); ?>"></div></div>
			<?php } ?>
			<ol class="studio-stops" style="--stop-count:<?php echo (int) count( $scenes ); ?>">
				<?php $position = 0; foreach ( $scenes as $key => $scene ) { ++$position; ?>
					<li><a href="#view-<?php echo esc_attr( $key ); ?>" data-studio-stop="<?php echo esc_attr( $key ); ?>" data-studio-image="<?php echo esc_url( $scene['image'] ); ?>">
						<?php if ( 'cut' === $design ) {
							if ( isset( $frame_marks[ $key ] ) ) { ?><span class="studio-frame-mark studio-frame-mark--<?php echo esc_attr( $key ); ?>" aria-hidden="true"><?php echo esc_html( $frame_marks[ $key ] ); ?></span><?php }
							elseif ( $scene['image'] ) { ?><img src="<?php echo esc_url( $scene['image'] ); ?>" alt="" width="150" height="90" decoding="async"><?php }
						} ?>
						<span class="studio-stop-number" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $position ) ); ?></span>
						<span class="studio-stop-label"><?php echo esc_html( $scene['label'] ); ?></span>
					</a></li>
				<?php } ?>
			</ol>
			<?php if ( 'cut' === $design ) { ?>
				<label data-studio-scrub hidden><span>Scrub the filmstrip</span><input type="range" min="0" max="<?php echo (int) ( count( $scenes ) - 1 ); ?>" value="0"></label>
			<?php } ?>
		</nav>
		<div class="studio-panels">
			<?php $position = 0; foreach ( $scenes as $key => $scene ) { ++$position; ?>
				<section id="view-<?php echo esc_attr( $key ); ?>" class="studio-panel" data-studio-panel="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $scene['title'] ); ?>">
					<?php if ( $scene['image'] && 'orbit' !== $design ) { ?><img class="studio-scene-image" src="<?php echo esc_url( $scene['image'] ); ?>" alt="" decoding="async"<?php if ( 1 !== $position ) { ?> loading="lazy"<?php } ?>><?php } ?>
					<div class="studio-panel-copy<?php echo 'movie-page' === $kind ? ' movie-page' : ''; ?>">
						<?php if ( isset( $scene['body'] ) ) { echo $scene['body']; } else { ?>
							<p class="studio-kicker"><?php echo esc_html( $scene['category'] ); ?></p>
							<h2><?php echo esc_html( $scene['title'] ); ?></h2>
							<p class="studio-deck"><?php echo esc_html( $scene['text'] ); ?></p>
							<a class="studio-action" href="<?php echo esc_url( $scene['url'] ); ?>"><?php echo esc_html( $scene['action'] ); ?> <span aria-hidden="true">↗</span></a>
						<?php } ?>
					</div>
				</section>
			<?php } ?>
			<div class="studio-step" data-studio-step hidden>
				<button type="button" data-studio-direction="-1">← Previous</button>
				<span data-studio-status role="status"></span>
				<button type="button" data-studio-direction="1">Next →</button>
			</div>
		</div>
	</div>
	<?php } ?>
</main>
<footer class="studio-footer">
	<div class="studio-footer-inner"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ComingSoon home ↗</a><div id="msc-source-footer"><?php echo Attribution::footer(); ?></div></div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
