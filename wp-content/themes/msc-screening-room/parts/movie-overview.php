<?php
use MSC\MovieEditorial;
$record = $args['record'];
$data = $record['data'];
$overview = msc_screen_overview( $record );
$faq = array();
if ( array_key_exists( 'post_credits_count', $data ) && is_numeric( $data['post_credits_count'] ) ) {
    $count = (int) $data['post_credits_count'];
    $faq[] = array( 'question' => 'Does it have a post-credits scene?', 'answer' => $count ? sprintf( _n( 'Yes. There is %d scene after the credits.', 'Yes. There are %d scenes after the credits.', $count ), $count ) : 'There are no post-credits scenes.' );
}
if ( ! empty( $data['content_rating'] ) && ! empty( $data['rating_reason'] ) ) {
    $faq[] = array( 'question' => 'Why is it rated ' . $data['content_rating'] . '?', 'answer' => $data['rating_reason'] );
}
foreach ( MovieEditorial::rows( $data, 'movie_faq' ) as $item ) {
    if ( is_string( $item['question'] ?? null ) && is_string( $item['answer'] ?? null ) && trim( $item['question'] ) && trim( $item['answer'] ) ) { $faq[] = $item; }
}
if ( ! $overview && ! $faq ) { return; }
?>
<section class="movie-section" id="overview" aria-labelledby="movie-overview-title">
    <h2 id="movie-overview-title">Overview</h2>
    <?php if ( $overview ) { ?><div class="catalog-description movie-prose"><?php echo $overview; ?></div><?php } ?>
    <?php if ( $faq ) { ?><div class="movie-quick-answers"><h3>Quick answers</h3>
        <?php foreach ( $faq as $item ) { ?><details class="movie-disclosure"><summary><?php echo esc_html( $item['question'] ); ?></summary><div class="movie-prose"><?php echo wp_kses_post( wpautop( $item['answer'] ) ); ?></div></details><?php } ?>
    </div><?php } ?>
</section>
