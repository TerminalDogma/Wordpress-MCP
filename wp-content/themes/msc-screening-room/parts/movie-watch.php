<?php
use MSC\{Attribution, StreamingServices};

$brands = StreamingServices::brands();
$logos = array();
foreach ( $brands as $key => list( $label, , $aliases ) ) {
    foreach ( array( $label, ...$aliases ) as $name ) {
        $logos[ StreamingServices::name( $name ) ] = array( 'src' => plugins_url( 'assets/services/' . $key . '.png', MSC_FILE ), 'ref' => array() );
    }
}
foreach ( StreamingServices::directory() as $service ) {
    if ( ! $service['logo'] ) { continue; }
    $brand = $brands[ $service['key'] ] ?? null;
    $names = $brand ? array( $service['title'], $brand[0], ...$brand[2] ) : array( $service['title'] );
    foreach ( $names as $name ) {
        $logos[ StreamingServices::name( $name ) ] = array( 'src' => $service['logo'], 'ref' => $service['logo_ref'] );
    }
}
?>
<section class="movie-watch" id="where-to-watch" aria-labelledby="movie-watch-title">
    <h2 id="movie-watch-title">Where to watch</h2>
    <div class="movie-watch-options">
        <?php foreach ( $args['watch'] as $offer ) {
            $logo = $logos[ StreamingServices::name( $offer['service'] ) ] ?? null;
            ?>
            <a href="<?php echo esc_url( $offer['url'] ); ?>" class="movie-watch-link">
                <?php if ( $logo ) { ?>
                    <img class="movie-watch-icon" src="<?php echo esc_url( $logo['src'] ); ?>" alt="" width="36" height="32" decoding="async">
                    <?php if ( $logo['ref'] ) { Attribution::collect_media( $logo['ref'], $offer['service'] . ' logo' ); } ?>
                <?php } else { ?>
                    <span class="movie-watch-icon movie-watch-initial" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( trim( $offer['service'] ), 0, 1 ) ) ); ?></span>
                <?php } ?>
                <span class="movie-watch-copy">
                    <strong><?php echo esc_html( $offer['service'] ); ?></strong>
                    <?php if ( $offer['kind'] || $offer['region'] ) { ?><small><?php echo esc_html( implode( ' · ', array_filter( array( $offer['kind'], $offer['region'] ) ) ) ); ?></small><?php } ?>
                    <?php if ( $offer['checked'] ) { ?><small>Checked <time datetime="<?php echo esc_attr( $offer['checked'] ); ?>"><?php echo esc_html( $offer['checked'] ); ?></time></small><?php } ?>
                </span>
            </a>
        <?php } ?>
    </div>
</section>
