<?php
get_header();
while ( have_posts() ) :
    the_post();
    $entry = get_post();
    if ( has_shortcode( $entry->post_content, \MSC\UpcomingReleases::SHORTCODE ) ) {
        ?><article class="release-guide-page">
            <h1><?php the_title(); ?></h1>
            <p class="article-byline"><?php echo esc_html( get_the_date() ); ?> · <?php the_author(); ?></p>
            <?php the_content(); get_template_part( 'parts/related-movies', null, array( 'post' => $entry ) ); ?>
        </article><?php
        continue;
    }
    $sample = get_post_meta( $entry->ID, '_msc_network_demo_slug', true ) || get_post_meta( $entry->ID, '_msc_mcu_article', true ) || get_post_meta( $entry->ID, '_msc_movie_preview', true );
    $categories = get_the_category();
    $label = $sample ? 'Presentation feature' : ( $categories[0]->name ?? '' );
    ?>
    <article class="news-article">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span>/</span><span>News and Features</span></nav>
        <?php if ( $label ) { ?><p class="eyebrow"><?php echo esc_html( $label ); ?></p><?php } ?>
        <h1><?php the_title(); ?></h1>
        <p class="article-byline"><?php the_author(); ?> · <?php echo esc_html( get_the_date() ); ?></p>
        <?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'article-art' ) ); } elseif ( $sample ) { msc_news_art( $entry, 'article-art' ); } ?>
        <div class="prose"><?php the_content(); ?></div>
        <?php get_template_part( 'parts/related-movies', null, array( 'post' => $entry ) ); ?>
        <a class="text-link" href="<?php echo esc_url( \MSC\NetworkSites::calendar_url() ); ?>">View the release calendar →</a>
    </article>
<?php endwhile; get_footer(); ?>
