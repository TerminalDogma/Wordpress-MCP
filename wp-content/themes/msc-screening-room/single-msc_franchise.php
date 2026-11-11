<?php
get_header();
while ( have_posts() ) { the_post(); $r = msc_screen_record(); if ( ! $r ) { continue; } $d = $r['data']; $members = \MSC\Franchises::members( $r );
$counts = array_count_values( array_column( $members, 'type' ) ); $backdrop = \MSC\Media::url( (array) ( $d['featured_image'] ?? array() ), 'full' );
?>
<article class="franchise-page"<?php if ( preg_match( '/^#[0-9a-fA-F]{6}$/D', $d['theme_color'] ?? '' ) ) { echo ' style="--accent:' . esc_attr( $d['theme_color'] ) . '"'; } ?>>
<header class="franchise-hero">
    <?php if ( $backdrop ) { ?><img class="franchise-backdrop" src="<?php echo esc_url( $backdrop ); ?>" alt=""><?php } ?>
    <div class="franchise-heading"><p class="eyebrow">Franchise collection</p><h1><?php echo esc_html( $d['title'] ); ?></h1><p class="franchise-counts"><?php $movie_count = (int) ( $counts['msc_movie'] ?? 0 ); echo esc_html( $movie_count . ' ' . ( 1 === $movie_count ? 'Movie' : 'Movies' ) ); ?> <span aria-hidden="true"> / </span><?php $show_count = (int) ( $counts['msc_show'] ?? 0 ); echo esc_html( $show_count . ' ' . ( 1 === $show_count ? 'TV Show' : 'TV Shows' ) ); ?></p><?php msc_screen_chips( $r ); ?></div>
    <div class="franchise-poster"><?php echo \MSC\Presentation::image( $r ); \MSC\Attribution::collect_image( $r ); \MSC\Attribution::collect_media( (array) ( $d['featured_image'] ?? array() ), $d['title'] ); ?></div>
</header>
<?php if ( trim( wp_strip_all_tags( $d['content'] ?? '' ) ) ) { ?><section class="franchise-overview reading-width"><h2>About this franchise</h2><div class="catalog-description"><?php echo msc_screen_overview( $r ); ?></div></section><?php } ?>
<section class="franchise-titles" aria-labelledby="franchise-titles-heading"><div class="section-heading"><h2 id="franchise-titles-heading">Movies and TV Shows</h2><span>Release order</span></div>
<?php if ( ! $members ) { ?><p>Titles will appear here when published.</p><?php } else { ?><ol class="franchise-grid"><?php foreach ( $members as $index => $member ) { $date = $member['franchise_date']; ?>
<li><article class="franchise-title"><a class="franchise-title-art" href="<?php echo esc_url( \MSC\Presentation::url( $member ) ); ?>"><?php echo \MSC\Presentation::image( $member, '', 'medium_large' ); echo \MSC\ReviewScores::badge( $member ); ?><span class="franchise-position" aria-label="Position <?php echo (int) $index + 1; ?>"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span></a><div class="franchise-title-copy"><p class="eyebrow"><?php echo 'msc_movie' === $member['type'] ? 'Movie' : 'TV Show'; ?><?php if ( $date ) { ?> · <time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( substr( $date, 0, 4 ) ); ?></time><?php } ?></p><h3><a href="<?php echo esc_url( \MSC\Presentation::url( $member ) ); ?>"><?php echo esc_html( $member['data']['title'] ); ?></a></h3></div></article></li>
<?php \MSC\Attribution::collect_image( $member ); } ?></ol><?php } ?></section>
</article>
<?php \MSC\Attribution::collect( $r ); }
get_footer();
