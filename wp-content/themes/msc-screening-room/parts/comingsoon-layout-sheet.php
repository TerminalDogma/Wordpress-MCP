<?php
$priority = array_flip( array( 'harbor-nine', 'red-mesa-review', 'last-meridian', 'celeste-rowe', 'second-signal-trailer', 'low-water-first-look', 'quiet-hours-streaming', 'avery-voss-interview', 'hollis-house-room-service' ) );
$stories = $args['stories'];
usort( $stories, static fn( WP_Post $a, WP_Post $b ) => array( $priority[ $a->post_name ] ?? PHP_INT_MAX, $a->menu_order, $a->ID ) <=> array( $priority[ $b->post_name ] ?? PHP_INT_MAX, $b->menu_order, $b->ID ) );
$formats = array( 'all' => 'All Stories', 'news' => 'News', 'recaps' => 'Recaps', 'criticism' => 'Reviews', 'features' => 'Interviews & Features', 'streaming' => 'Streaming', 'trailers' => 'Trailers & First Looks' );
$names = array_fill_keys( array_map( 'msc_cs_preview_format', $stories ), true );
$formats = array_intersect_key( $formats, array( 'all' => true ) + $names );
$filter = is_string( $_GET['cs_format'] ?? null ) ? sanitize_key( wp_unslash( $_GET['cs_format'] ) ) : '';
$filter = isset( $formats[ $filter ] ) ? $filter : 'all';
$expanded = '1' === ( $_GET['cs_all'] ?? '' );
$limit = 9;
$count = 0;
?>
<header class="sheet-title"><h1><?php echo esc_html( $args['title'] ); ?></h1></header>

<?php if ( ! $stories ) { ?><p>No preview stories are published yet.</p><?php return; } ?>

<section class="sheet-wall" id="the-feed" aria-label="Browse ComingSoon coverage" data-cs-feed data-limit="<?php echo (int) $limit; ?>">
	<nav aria-label="Coverage format" class="layout-tabs sheet-formats">
		<?php foreach ( $formats as $key => $label ) { ?>
			<a href="<?php echo esc_url( add_query_arg( 'cs_format', 'all' === $key ? false : $key, $args['url'] ) . '#the-feed' ); ?>" data-cs-filter="<?php echo esc_attr( $key ); ?>"<?php if ( $filter === $key ) { ?> aria-current="true"<?php } ?>><?php echo esc_html( $label ); ?></a>
		<?php } ?>
	</nav>
	<p class="screen-reader-text" data-cs-feed-status role="status"></p>
	<div class="sheet-grid">
		<?php foreach ( $stories as $story ) {
			$format = msc_cs_preview_format( $story );
			$match = 'all' === $filter || $format === $filter;
			if ( $match ) { ++$count; }
			?>
			<article class="sheet-tile" data-cs-format="<?php echo esc_attr( $format ); ?>"<?php if ( ! $match || ( ! $expanded && $count > $limit ) ) { ?> hidden<?php } ?>>
				<a href="<?php echo esc_url( get_permalink( $story ) ); ?>">
					<?php msc_cs_preview_art( $story, 1 === $count && $match ); ?>
					<div class="sheet-tile-copy">
						<p class="layout-label"><?php echo esc_html( msc_cs_preview_meta( $story, 'label' ) ); ?></p>
						<h2><?php echo esc_html( get_the_title( $story ) ); ?></h2>
					</div>
				</a>
			</article>
		<?php } ?>
	</div>
	<a class="sheet-more" href="<?php echo esc_url( add_query_arg( array( 'cs_format' => 'all' === $filter ? false : $filter, 'cs_all' => '1' ), $args['url'] ) . '#the-feed' ); ?>" data-cs-feed-more<?php if ( $expanded || $count <= $limit ) { ?> hidden<?php } ?>>Show all matching stories <span aria-hidden="true">↓</span></a>
</section>
