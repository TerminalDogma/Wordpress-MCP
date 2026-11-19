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
    $permalink = Presentation::url( $record );
    $read_page = static function ( string $key ): int {
        $value = $_GET[ $key ] ?? '';
        return is_string( $value ) && preg_match( '/^[1-9][0-9]{0,6}$/D', $value ) ? (int) $value : 1;
    };
    $title_key = 'franchise_titles_page';
    $article_key = 'franchise_articles_page';
    $titles_per_page = 8;
    $title_pages = (int) ceil( count( $members ) / $titles_per_page );
    $title_page = min( max( 1, $title_pages ), $read_page( $title_key ) );
    $title_offset = ( $title_page - 1 ) * $titles_per_page;
    $article_page = $read_page( $article_key );
    $articles = Franchises::articles( $record, $article_page );
    $sections = array(
        $title_key => array( 'id' => 'franchise-titles', 'label' => 'Movies and TV Shows', 'page' => $title_page, 'pages' => $title_pages ),
        $article_key => array( 'id' => 'franchise-articles', 'label' => 'Recent Articles', 'page' => $article_page, 'pages' => $articles->max_num_pages ),
    );
    $page_url = static function ( string $key, int $page ) use ( $sections, $permalink ): string {
        $args = array();
        foreach ( $sections as $name => $section ) {
            $number = $name === $key ? $page : $section['page'];
            if ( $number > 1 ) { $args[ $name ] = $number; }
        }
        return add_query_arg( $args, $permalink ) . '#' . $sections[ $key ]['id'];
    };
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

        <?php foreach ( $sections as $key => $section ) {
            $is_titles = $title_key === $key;
            if ( ! $is_titles && ! $articles->posts && 1 === $article_page ) { continue; }
            $id = $section['id'];
            ?>
            <section class="franchise-collection <?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" data-franchise-collection aria-labelledby="<?php echo esc_attr( $id . '-heading' ); ?>">
                <div class="section-heading">
                    <h2 id="<?php echo esc_attr( $id . '-heading' ); ?>"><?php echo esc_html( $section['label'] ); ?></h2>
                    <?php if ( $is_titles ) { ?><span>Release order</span><?php } ?>
                </div>
                <?php if ( $is_titles && $members ) { ?>
                    <ol class="franchise-grid" start="<?php echo $title_offset + 1; ?>" data-franchise-items>
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
                <?php } elseif ( ! $is_titles && $articles->posts ) { ?>
                    <ul class="franchise-articles-grid" data-franchise-items>
                        <?php foreach ( $articles->posts as $article ) { ?>
                            <li><article>
                                <a class="franchise-article" href="<?php echo esc_url( get_permalink( $article ) ); ?>">
                                    <?php echo get_the_post_thumbnail( $article, 'medium_large', array( 'alt' => '' ) ); ?>
                                    <div class="franchise-article-copy">
                                        <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $article ) ); ?>"><?php echo esc_html( get_the_date( '', $article ) ); ?></time>
                                        <h3><?php echo esc_html( get_the_title( $article ) ); ?></h3>
                                    </div>
                                </a>
                            </article></li>
                        <?php } ?>
                    </ul>
                <?php } else { ?><p><?php echo $is_titles ? 'Titles will appear here when published.' : 'No articles on this page.'; ?></p><?php } ?>
                <?php if ( $section['page'] > 1 || $section['page'] < $section['pages'] ) { ?>
                    <nav class="franchise-pagination" aria-label="<?php echo esc_attr( $section['label'] . ' pagination' ); ?>">
                        <?php if ( $section['page'] > 1 ) { ?>
                            <a class="text-link" href="<?php echo esc_url( $page_url( $key, $section['pages'] ? $section['page'] - 1 : 1 ) ); ?>">Previous</a>
                        <?php } ?>
                        <?php if ( $section['page'] < $section['pages'] ) { ?>
                            <a class="franchise-more" data-franchise-more aria-controls="<?php echo esc_attr( $id ); ?>" href="<?php echo esc_url( $page_url( $key, $section['page'] + 1 ) ); ?>">Show more <span class="screen-reader-text"><?php echo $is_titles ? 'titles' : 'articles'; ?></span></a>
                        <?php } ?>
                    </nav>
                <?php } ?>
            </section>
        <?php } ?>
    </article>
    <?php Attribution::collect( $record );
}
get_footer();
