<?php
use MSC\{Attribution, Franchises, Media, Presentation, ReviewScores, Timelines};

get_header();
while ( have_posts() ) {
    the_post();
    $record = msc_screen_record();
    if ( ! $record ) { continue; }
    $data = $record['data'];
    $members = Franchises::members( $record );
    $counts = array_count_values( array_column( $members, 'type' ) );
    $backdrop = Media::url( (array) ( $data['featured_image'] ?? array() ), 'full' );
    $timeline_links = Timelines::related( $record );
    $titles_per_page = 8;
    $title_pages = (int) ceil( count( $members ) / $titles_per_page );
    $title_page = min( max( 1, $title_pages ), msc_collection_page( 'franchise_titles_page' ) );
    $title_offset = ( $title_page - 1 ) * $titles_per_page;
    ?>
    <article class="franchise-page"<?php if ( preg_match( '/^#[0-9a-fA-F]{6}$/D', $data['theme_color'] ?? '' ) ) { echo ' style="--accent:' . esc_attr( $data['theme_color'] ) . '"'; } ?>>
        <header class="franchise-hero">
            <?php if ( $backdrop ) { ?><img class="franchise-backdrop" src="<?php echo esc_url( $backdrop ); ?>" alt=""><?php } ?>
            <div class="franchise-heading">
                <p class="eyebrow">Franchise collection</p>
                <h1><?php echo esc_html( $data['title'] ); ?></h1>
                <p class="franchise-counts">
                    <?php $movie_count = (int) ( $counts['msc_movie'] ?? 0 ); echo esc_html( $movie_count . ' ' . ( 1 === $movie_count ? 'Movie' : 'Movies' ) ); ?>
                    <span aria-hidden="true"> / </span>
                    <?php $show_count = (int) ( $counts['msc_show'] ?? 0 ); echo esc_html( $show_count . ' ' . ( 1 === $show_count ? 'TV Show' : 'TV Shows' ) ); ?>
                </p>
                <?php msc_screen_chips( $record ); ?>
                <?php get_template_part( 'parts/timeline-links', null, array( 'timelines' => $timeline_links, 'link_to_header' => true ) ); ?>
            </div>
            <div class="franchise-poster">
                <?php echo Presentation::image( $record ); Attribution::collect_image( $record ); Attribution::collect_media( (array) ( $data['featured_image'] ?? array() ), $data['title'] ); ?>
            </div>
        </header>
        <?php if ( trim( wp_strip_all_tags( $data['content'] ?? '' ) ) ) { ?>
            <section class="franchise-overview reading-width">
                <h2>About this franchise</h2><div class="catalog-description"><?php echo msc_screen_overview( $record ); ?></div>
            </section>
        <?php } ?>

        <section class="franchise-collection franchise-titles" id="franchise-titles" data-catalog-collection aria-labelledby="franchise-titles-heading">
            <div class="section-heading"><h2 id="franchise-titles-heading">Movies and TV Shows</h2><span>Release order</span></div>
            <?php if ( $members ) { ?>
                <ol class="franchise-grid" start="<?php echo $title_offset + 1; ?>" data-collection-items>
                    <?php foreach ( array_slice( $members, $title_offset, $titles_per_page, true ) as $index => $member ) {
                        $date = $member['franchise_date'];
                        $url = Presentation::url( $member );
                        ?>
                        <li><article class="franchise-title">
                            <a class="franchise-title-art" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $member['data']['title'] ); ?>">
                                <?php echo Presentation::image( $member, '', 'medium_large' ); echo ReviewScores::badge( $member ); ?>
                                <span class="franchise-position" aria-hidden="true"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
                            </a>
                            <div class="franchise-title-copy">
                                <p class="eyebrow"><?php echo 'msc_movie' === $member['type'] ? 'Movie' : 'TV Show'; ?><?php if ( $date ) { ?> · <time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( substr( $date, 0, 4 ) ); ?></time><?php } ?></p>
                                <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $member['data']['title'] ); ?></a></h3>
                            </div>
                        </article></li>
                        <?php Attribution::collect_image( $member );
                    } ?>
                </ol>
            <?php } else { ?><p>Titles will appear here when published.</p><?php } ?>
            <?php get_template_part( 'parts/collection-pagination', null, array(
                'id' => 'franchise-titles', 'key' => 'franchise_titles_page', 'page' => $title_page, 'pages' => $title_pages, 'label' => 'titles',
            ) ); ?>
        </section>
        <?php get_template_part( 'parts/recent-articles', null, array( 'record' => $record ) ); ?>
    </article>
    <?php Attribution::collect( $record );
}
get_footer();
