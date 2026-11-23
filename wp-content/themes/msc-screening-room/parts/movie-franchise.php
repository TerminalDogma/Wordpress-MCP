<?php
use MSC\{MovieEditorial, Presentation};
$record = $args['record'];
if ( ! $args['franchises'] && ! $args['timelines'] && empty( $record['data']['franchise_context'] ) ) { return; }
?>
<section class="movie-section" id="franchise-position">
    <h2>Franchise</h2>
    <?php if ( ! empty( $record['data']['franchise_context'] ) ) { ?><p class="movie-franchise-context"><?php echo esc_html( $record['data']['franchise_context'] ); ?></p><?php } ?>
    <?php foreach ( $args['franchises'] as $franchise ) {
        $orders = MovieEditorial::franchise_orders( $record, $franchise, $args['timelines'] );
        $phase = $orders['Story order']['phase'] ?? '';
        ?>
        <div class="movie-franchise-strip" data-franchise-order>
            <div class="movie-franchise-title"><h3><a href="<?php echo esc_url( Presentation::url( $franchise ) ); ?>"><?php echo esc_html( $franchise['data']['title'] ); ?> <span aria-hidden="true">↗</span></a></h3>
                <?php if ( $phase ) { ?><span class="movie-cert"><?php echo esc_html( $phase ); ?></span><?php } ?>
            </div>
            <?php if ( count( $orders ) > 1 ) { ?><div class="movie-order-switch" hidden aria-label="Franchise order">
                <?php foreach ( array_keys( $orders ) as $index => $label ) { ?><button type="button" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button><?php } ?>
            </div><?php } ?>
            <?php foreach ( $orders as $label => $order ) { ?>
                <div class="movie-order-panel">
                    <p class="movie-order-position"><?php echo esc_html( $label . ' · #' . $order['position'] ); ?><?php if ( ! empty( $order['date'] ) ) { ?> · <?php echo esc_html( $order['date'] ); ?><?php } ?></p>
                    <div class="movie-order-neighbors">
                        <?php foreach ( array( 'prev' => 'Previous', 'next' => 'Next' ) as $key => $direction ) { ?>
                            <div><span><?php echo esc_html( $direction ); ?></span>
                                <?php if ( $order[ $key ] ) { ?><a href="<?php echo esc_url( Presentation::url( $order[ $key ] ) ); ?>"><?php echo esc_html( $order[ $key ]['data']['title'] ); ?></a><?php } else { ?><span aria-label="<?php echo esc_attr( 'No ' . strtolower( $direction ) . ' title' ); ?>">—</span><?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if ( ! empty( $order['timeline'] ) ) { ?><a class="text-link" href="<?php echo esc_url( $order['timeline'] ); ?>">Open this entry in the story timeline ↗</a><?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
    <?php get_template_part( 'parts/timeline-links', null, array( 'timelines' => $args['timelines'] ) ); ?>
</section>
