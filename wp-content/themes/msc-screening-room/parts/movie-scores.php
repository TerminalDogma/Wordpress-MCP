<?php
use MSC\{Attribution, ReleaseCalendar, Reviews};

$data = $args['record']['data'];
$scores = $args['scores'];
if ( ! $scores ) { return; }
$checked = ReleaseCalendar::date( substr( (string) ( $data['score_updated_at'] ?? '' ), 0, 10 ) );
?>
<section class="movie-section review-scores" id="review-scores">
    <h2>Review scores</h2>
    <div class="review-score-grid movie-score-grid">
        <?php foreach ( $scores as $row ) {
            $provider = $row['provider'];
            $editorial = 'editorial' === $provider;
            $sample = $editorial && get_post_meta( $row['review_id'], '_msc_movie_preview', true );
            $archive = $editorial ? \MSC\MovieEditorial::link( get_post_meta( $row['review_id'], '_msc_review_archive_url', true ) ) : '';
            $tomatoes = 'rotten_tomatoes' === $provider;
            $reviews = (int) ( $data[ $provider . '_review_count' ] ?? 0 );
            $quote = $editorial ? (string) get_post_meta( $row['review_id'], '_msc_review_verdict', true ) : '';
            if ( $editorial && ! $quote ) { $quote = get_the_excerpt( $row['review_id'] ); }
            if ( ! $editorial && $row['url'] ) { Attribution::collect_html( $data['title'] . ' — ' . $row['name'], '<a href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['name'] ) . '</a>' ); }
            ?>
            <article class="review-score-card<?php echo $editorial ? ' editorial-score' : ''; ?><?php if ( $row['award'] ) { echo ' award-' . esc_attr( $row['award'] ); } ?>" id="<?php echo esc_attr( 'movie-score-' . $provider ); ?>">
                <div class="movie-score-top">
                    <div>
                        <h3><?php echo esc_html( $row['name'] ); ?></h3>
                        <p><?php echo $editorial ? ( $sample ? 'Sample review score' : 'Our review' ) : ( $tomatoes ? 'Tomatometer®' : 'Metascore' ); ?></p>
                    </div>
                    <p class="review-score-value"><?php echo esc_html( Reviews::number( $row['score'] ) ); ?><small><?php echo $tomatoes ? '%' : '/' . (int) $row['max']; ?></small></p>
                </div>
                <?php if ( $quote ) { ?><blockquote><?php echo esc_html( wp_trim_words( $quote, 42 ) ); ?></blockquote><?php } ?>
                <?php if ( $row['award'] ) { ?><p class="review-award-label"><?php echo esc_html( ucfirst( $row['award'] ) . ' recommendation' ); ?></p><?php } ?>
                <?php if ( ! $editorial && $reviews ) { ?><p class="movie-score-context"><?php echo esc_html( number_format_i18n( $reviews ) . ' critic reviews' ); ?></p><?php } ?>
                <?php if ( $tomatoes && isset( $data['rotten_tomatoes_audience_score'] ) ) { ?>
                    <p class="movie-score-context"><strong><?php echo esc_html( Reviews::number( (float) $data['rotten_tomatoes_audience_score'] ) ); ?>%</strong> Popcornmeter<?php if ( ! empty( $data['rotten_tomatoes_audience_count'] ) ) { ?> · <?php echo esc_html( number_format_i18n( (int) $data['rotten_tomatoes_audience_count'] ) . ( empty( $data['rotten_tomatoes_audience_count_plus'] ) ? '' : '+' ) ); ?> ratings<?php } ?></p>
                <?php } ?>
                <?php if ( 'metacritic' === $provider && isset( $data['metacritic_positive_count'], $data['metacritic_mixed_count'] ) ) { ?>
                    <p class="movie-score-context"><?php echo esc_html( number_format_i18n( (int) $data['metacritic_positive_count'] ) . ' positive · ' . number_format_i18n( (int) $data['metacritic_mixed_count'] ) . ' mixed' ); ?></p>
                <?php } ?>
                <div class="movie-score-source">
                    <?php if ( $row['url'] ) { ?><a class="text-link" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo $editorial ? 'Read our review' : 'View source score'; ?> ↗</a><?php } ?>
                    <?php if ( $archive ) { ?><a class="text-link" href="<?php echo esc_url( $archive ); ?>">Read the archived review ↗</a><?php } ?>
                    <?php if ( ! $editorial && $checked ) { ?><small>Checked <time datetime="<?php echo esc_attr( $checked ); ?>"><?php echo esc_html( $checked ); ?></time></small><?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>
</section>
