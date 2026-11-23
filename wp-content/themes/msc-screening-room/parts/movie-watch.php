<section class="movie-watch" id="where-to-watch" aria-labelledby="movie-watch-title">
    <h2 id="movie-watch-title">Where to watch</h2>
    <div class="movie-watch-options">
        <?php foreach ( $args['watch'] as $offer ) { ?>
            <a href="<?php echo esc_url( $offer['url'] ); ?>" class="movie-watch-link">
                <strong><?php echo esc_html( $offer['service'] ); ?></strong>
                <?php if ( $offer['kind'] || $offer['region'] ) { ?><small><?php echo esc_html( implode( ' · ', array_filter( array( $offer['kind'], $offer['region'] ) ) ) ); ?></small><?php } ?>
                <?php if ( $offer['checked'] ) { ?><small>Checked <time datetime="<?php echo esc_attr( $offer['checked'] ); ?>"><?php echo esc_html( $offer['checked'] ); ?></time></small><?php } ?>
            </a>
        <?php } ?>
    </div>
</section>
