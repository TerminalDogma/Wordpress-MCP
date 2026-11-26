<?php
use MSC\{Attribution, CatalogArticles, MovieEditorial, Presentation, ReleaseCalendar, Reviews};

$title_query = is_string( $_GET['title_query'] ?? null ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['title_query'] ) ), 0, 100 ) : '';
$options = array( 'all' => 'Movies and TV', 'msc_movie' => 'Movies', 'msc_show' => 'TV Shows' );
$type = is_string( $_GET['title_type'] ?? null ) ? sanitize_key( wp_unslash( $_GET['title_type'] ) ) : '';
$type = isset( $options[ $type ] ) ? $type : 'all';
$base = array(
	'post_type' => 'all' === $type ? array( 'msc_movie', 'msc_show' ) : $type,
	'post_status' => 'publish', 'has_password' => false,
	'posts_per_page' => 13,
);
$featured = array( 'iron-man', 'the-last-meridian', 'harbor-nine', 'northstar-station', 'avengers-doomsday' );
$fallback = array( 'the-last-meridian' => array( 'last-meridian', 'meridian-effects' ), 'harbor-nine' => array( 'harbor-nine', 'harbor-nine-the-mooring-line' ) );
$samples = array_column( $args['stories'], null, 'post_name' );
$is_search = '' !== $title_query || 'all' !== $type;
$posts = match ( true ) {
	'' !== $title_query => get_posts( $base + array( 's' => $title_query, 'search_columns' => array( 'post_title' ), 'suppress_filters' => false ) ),
	$is_search => get_posts( $base ),
	default => get_posts( $base + array( 'post_name__in' => $featured, 'orderby' => 'post_name__in' ) ) ?: get_posts( $base ),
};
$truncated = count( $posts ) > 12;
$records = array();
foreach ( array_slice( $posts, 0, 12 ) as $title_post ) {
	$shared = msc_from_post( $title_post->ID );
	$record = $shared ? Presentation::record( $shared['id'] ) : null;
	if ( $record ) { $records[] = array( $title_post, $record ); }
}
?>
<header class="index-title">
	<h1><?php echo esc_html( $args['title'] ); ?></h1>
	<form class="index-search" action="<?php echo esc_url( $args['url'] ); ?>" method="get" role="search" aria-label="Film and TV catalog">
		<div><label for="index-query">Find a movie or show</label><input id="index-query" type="search" name="title_query" value="<?php echo esc_attr( $title_query ); ?>" placeholder="Try Iron Man" maxlength="100"></div>
		<div><label for="index-type">Title type</label><select id="index-type" name="title_type"><?php foreach ( $options as $value => $label ) { ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $type ); ?>><?php echo esc_html( $label ); ?></option><?php } ?></select></div>
		<button type="submit">Find titles <span aria-hidden="true">→</span></button>
	</form>
</header>

<section class="index-board" aria-labelledby="index-board-title">
	<div class="index-board-head">
		<h2 id="index-board-title"><?php echo $is_search ? 'Catalog Results' : 'Featured Titles'; ?></h2>
		<?php if ( $truncated ) { ?><p>Showing the first 12 matches</p><?php } ?>
		<?php if ( $is_search ) { ?><a href="<?php echo esc_url( $args['url'] ); ?>">Clear search</a><?php } ?>
	</div>
	<?php if ( ! $records ) { ?><p class="index-empty">No published titles match this search.</p><?php } ?>
	<?php foreach ( $records as list( $title_post, $record ) ) {
		$data = $record['data'];
		$movie = 'msc_movie' === $record['type'];
		$url = Presentation::url( $record );
		$date = $movie ? MovieEditorial::theatrical_release( $data )['date'] : ReleaseCalendar::date( $data['premiere_date'] ?? '' );
		$coverage = CatalogArticles::query( $record, 1, '', 'DESC', 3 )->posts;
		if ( ! $coverage ) {
			foreach ( $fallback[ $title_post->post_name ] ?? array() as $slug ) { if ( isset( $samples[ $slug ] ) ) { $coverage[] = $samples[ $slug ]; } }
		}
		$review = Reviews::own( $record );
		?>
		<article class="index-row">
			<div class="index-film">
				<?php echo Presentation::image( $record, 'index-art', 'medium_large' ); Attribution::collect_image( $record ); ?>
				<div>
					<p class="layout-label"><?php echo $movie ? 'Movie' : 'TV Show'; ?></p>
					<h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $data['title'] ); ?></a></h3>
					<?php if ( $date ) { ?><time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( wp_date( 'M j, Y', strtotime( $date . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></time><?php } ?>
				</div>
			</div>
			<div class="index-junction" aria-hidden="true"><span></span></div>
			<div class="index-coverage">
				<h4>Coverage</h4>
				<?php if ( $coverage || $review ) { ?><ul>
					<?php if ( $review ) { ?><li><a href="<?php echo esc_url( $review['url'] ?: $url . '#review-scores' ); ?>"><span class="index-review"><?php echo esc_html( get_post_meta( $review['review_id'], '_msc_movie_preview', true ) ? 'Sample Review' : 'ComingSoon Review' ); ?></span></a></li><?php } ?>
					<?php foreach ( $coverage as $story ) { ?><li><a href="<?php echo esc_url( get_permalink( $story ) ); ?>"><?php echo esc_html( get_the_title( $story ) ); ?></a></li><?php } ?>
				</ul><?php } else { ?><p>No linked articles yet.</p><?php } ?>
			</div>
		</article>
		<?php Attribution::collect( $record, array( 'title', $movie ? 'release_date' : 'premiere_date' ) ); ?>
	<?php } ?>
</section>
