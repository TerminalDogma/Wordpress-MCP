<?php
use MSC\{Attribution, MovieEditorial, ReleaseCalendar};
$data = $args['record']['data'];
$events = MovieEditorial::releases( $data );
$amounts = array();
foreach ( array( 'film_budget' => 'Production budget', 'domestic_opening' => 'Domestic opening', 'worldwide_gross' => 'Worldwide box office' ) as $key => $label ) {
    if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) { $amounts[ $label ] = (float) $data[ $key ]; }
}
$currency = strtoupper( $data['box_office_currency'] ?? '' );
$box_office = MovieEditorial::link( $data['box_office_url'] ?? '' );
if ( ! $events && ! $amounts ) { return; }
?>
<section class="movie-section" id="movie-details">
    <h2>Details</h2>
    <?php if ( $amounts ) { ?>
        <h3>Box office<?php if ( $currency ) { ?> <small><?php echo esc_html( $currency ); ?></small><?php } ?></h3>
        <dl class="movie-box-office">
            <?php foreach ( $amounts as $label => $value ) { ?><div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( ( 'USD' === $currency ? '$' : '' ) . number_format_i18n( $value ) ); ?></dd></div><?php } ?>
        </dl>
        <?php if ( $box_office ) { Attribution::collect_html( $data['title'] . ' — Box office', '<a href="' . esc_url( $box_office ) . '">Box office source</a>' ); } ?>
    <?php } ?>
    <?php if ( $events ) { ?>
        <h3>Release history</h3>
        <ul class="movie-releases">
            <?php foreach ( $events as $event ) { ?>
                <li><time datetime="<?php echo esc_attr( $event['date'] ); ?>"><?php echo esc_html( wp_date( 'M j, Y', strtotime( $event['date'] . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></time><div><strong><?php echo esc_html( $event['label'] ); ?></strong><?php if ( $event['region'] ) { ?><span><?php echo esc_html( $event['region'] ); ?></span><?php } ?></div></li>
                <?php if ( $event['url'] ) { Attribution::collect_html( $data['title'] . ' — ' . $event['label'], '<a href="' . esc_url( $event['url'] ) . '">Release date source</a>' ); } ?>
            <?php } ?>
        </ul>
    <?php } ?>
</section>
