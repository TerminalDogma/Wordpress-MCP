<?php
use MSC\CatalogArticles;

$record = $args['record'];
$is_franchise = 'msc_franchise' === $record['type'];
$id = $is_franchise ? 'franchise-articles' : 'recent-articles';
$key = $is_franchise ? 'franchise_articles_page' : 'recent_articles_page';
$page = msc_collection_page( $key );
$articles = CatalogArticles::query( $record, $page );
if ( ! $articles->posts && 1 === $page ) { return; }
?>
<section class="recent-articles" id="<?php echo esc_attr( $id ); ?>" data-catalog-collection aria-labelledby="<?php echo esc_attr( $id . '-heading' ); ?>">
    <div class="section-heading"><h2 id="<?php echo esc_attr( $id . '-heading' ); ?>">Recent Articles</h2></div>
    <?php if ( $articles->posts ) { ?>
        <ul class="recent-articles-grid" data-collection-items>
            <?php foreach ( $articles->posts as $article ) { ?>
                <li><article>
                    <a class="recent-article" href="<?php echo esc_url( get_permalink( $article ) ); ?>">
                        <?php echo get_the_post_thumbnail( $article, 'medium_large', array( 'alt' => '' ) ); ?>
                        <div class="recent-article-copy">
                            <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $article ) ); ?>"><?php echo esc_html( get_the_date( '', $article ) ); ?></time>
                            <h3><?php echo esc_html( get_the_title( $article ) ); ?></h3>
                        </div>
                    </a>
                </article></li>
            <?php } ?>
        </ul>
    <?php } else { ?><p>No articles on this page.</p><?php } ?>
    <?php get_template_part( 'parts/collection-pagination', null, array(
        'id' => $id, 'key' => $key, 'page' => $page, 'pages' => $articles->max_num_pages, 'label' => 'articles',
    ) ); ?>
</section>
