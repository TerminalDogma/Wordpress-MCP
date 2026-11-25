<?php
$stories = $args['stories'];
$url = $args['url'];
$formats = array(
	'all' => 'All',
	'news' => 'News',
	'recaps' => 'Recaps',
	'criticism' => 'Criticism',
	'features' => 'Interviews & Features',
	'streaming' => 'Streaming',
	'trailers' => 'Trailers',
);
$available = array_fill_keys( array_map( 'msc_cs_preview_format', $stories ), true );
$formats = array_intersect_key( $formats, array( 'all' => true ) + $available );
$filter = is_string( $_GET['cs_format'] ?? null ) ? sanitize_key( wp_unslash( $_GET['cs_format'] ) ) : 'all';
if ( ! isset( $formats[ $filter ] ) ) { $filter = 'all'; }
$expanded = '1' === ( $_GET['cs_all'] ?? '' );
$limit = 8;
$count = 0;

usort( $stories, static fn( WP_Post $a, WP_Post $b ) => array( $b->post_date_gmt, $b->ID ) <=> array( $a->post_date_gmt, $a->ID ) );
?>
<section id="the-feed" class="cs-feed" aria-labelledby="cs-feed-title" data-cs-feed data-limit="<?php echo esc_attr( $limit ); ?>">
	<div class="cs-frame">
		<div class="cs-feed-heading">
			<h2 id="cs-feed-title"><?php echo esc_html( $args['title'] ?? 'The Feed' ); ?></h2>
			<nav class="cs-feed-filters" aria-label="Filter stories">
				<?php foreach ( $formats as $key => $label ) { ?>
					<a href="<?php echo esc_url( add_query_arg( 'cs_format', 'all' === $key ? false : $key, $url ) . '#the-feed' ); ?>" data-cs-filter="<?php echo esc_attr( $key ); ?>"<?php if ( $filter === $key ) { ?> aria-current="true"<?php } ?>><?php echo esc_html( $label ); ?></a>
				<?php } ?>
			</nav>
		</div>
		<p class="screen-reader-text" role="status" data-cs-feed-status></p>
		<div class="cs-feed-list">
			<?php foreach ( $stories as $story ) {
				$match = 'all' === $filter || $filter === msc_cs_preview_format( $story );
				if ( $match ) { ++$count; }
				msc_cs_preview_card( $story, 'feed', ! $match || ( ! $expanded && $count > $limit ) );
			} ?>
		</div>
		<a class="cs-feed-more" href="<?php echo esc_url( add_query_arg( array( 'cs_format' => 'all' === $filter ? false : $filter, 'cs_all' => '1' ), $url ) . '#the-feed' ); ?>" data-cs-feed-more<?php if ( $expanded || $count <= $limit ) { ?> hidden<?php } ?>>Show all matching stories <span aria-hidden="true">↓</span></a>
	</div>
</section>
