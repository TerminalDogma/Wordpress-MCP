<?php
use MSC\{CatalogArticles, Presentation};

$movies = CatalogArticles::related_movies( $args['post'] );
if ( ! $movies ) { return; }
?>
<section class="article-movies" aria-labelledby="article-movies-heading">
    <h2 id="article-movies-heading">Movies in this article</h2>
    <ul>
        <?php foreach ( $movies as $movie ) { ?><li><a href="<?php echo esc_url( Presentation::url( $movie ) ); ?>"><?php echo esc_html( $movie['data']['title'] ); ?> <span aria-hidden="true">→</span></a></li><?php } ?>
    </ul>
</section>
