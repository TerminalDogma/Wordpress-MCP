<?php
use MSC\CatalogArticles;

$record = $args['record'];
$key = 'recent_articles_page';
$page = msc_collection_page( $key );
$id = 'recent-articles';
$kinds = CatalogArticles::COVERAGE;
$requested = $_GET['coverage_type'] ?? '';
$kind = is_string( $requested ) && isset( $kinds[ $requested ] ) ? $requested : '';
$oldest = 'oldest' === ( $_GET['coverage_order'] ?? '' );
$coverage = CatalogArticles::movie_coverage( $record, $page, $kind, $oldest );
$posts = $coverage['posts'];
if ( ! $kind && 1 === $page && ! $posts ) { return; }
$filters = static function ( array $values ) use ( $key, $id ): string {
    return esc_url( add_query_arg( $values, remove_query_arg( $key ) ) . '#' . $id );
};
?>
<section class="movie-section movie-coverage" id="<?php echo esc_attr( $id ); ?>" data-catalog-collection>
    <h2>Coverage</h2>
    <div class="movie-coverage-controls">
        <nav aria-label="Coverage type">
            <?php foreach ( array( '' => 'All coverage' ) + $kinds as $value => $label ) { ?>
                <a href="<?php echo $filters( array( 'coverage_type' => $value !== '' ? $value : false ) ); ?>"<?php if ( $value === $kind ) { ?> aria-current="true"<?php } ?>><?php echo esc_html( $label ); ?></a>
            <?php } ?>
        </nav>
        <nav aria-label="Coverage order">
            <a href="<?php echo $filters( array( 'coverage_order' => false ) ); ?>"<?php if ( ! $oldest ) { ?> aria-current="true"<?php } ?>>Newest first</a>
            <a href="<?php echo $filters( array( 'coverage_order' => 'oldest' ) ); ?>"<?php if ( $oldest ) { ?> aria-current="true"<?php } ?>>Oldest first</a>
        </nav>
    </div>
    <?php if ( $posts ) { ?>
        <ul class="movie-coverage-list" data-collection-items>
            <?php foreach ( $posts as $article ) {
                $thumbnail = get_the_post_thumbnail( $article, 'medium', array( 'alt' => '' ) );
                $author = get_the_author_meta( 'display_name', (int) $article->post_author );
                $sample = get_post_meta( $article->ID, '_msc_mcu_article', true ) || get_post_meta( $article->ID, '_msc_movie_preview', true );
                ?>
                <li><article class="movie-coverage-card">
                    <?php if ( $thumbnail ) { echo $thumbnail; } elseif ( $sample ) { msc_news_art( $article ); } ?>
                    <div>
                        <p class="movie-article-kind"><?php echo esc_html( CatalogArticles::coverage_kind( $article ) ); ?></p>
                        <h3><a href="<?php echo esc_url( get_permalink( $article ) ); ?>"><?php echo esc_html( get_the_title( $article ) ); ?></a></h3>
                        <p class="movie-article-meta"><?php if ( $author ) { echo esc_html( $author ) . ' · '; } ?><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $article ) ); ?>"><?php echo esc_html( get_the_date( '', $article ) ); ?></time></p>
                    </div>
                </article></li>
            <?php } ?>
        </ul>
    <?php } else { ?><p>No <?php echo esc_html( $kind ? strtolower( $kinds[ $kind ] ) : 'articles' ); ?> yet.</p><?php } ?>
    <?php get_template_part( 'parts/collection-pagination', null, array( 'id' => $id, 'key' => $key, 'page' => $page, 'pages' => $coverage['pages'], 'label' => 'articles' ) ); ?>
</section>
