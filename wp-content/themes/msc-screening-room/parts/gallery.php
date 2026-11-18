<?php
use MSC\Attribution;

$record = $args['record'];
$images = array();
foreach ( $record['data']['promotional_images'] ?? array() as $ref ) {
    $url = msc_media_url( (array) $ref );
    if ( $url ) {
        $images[] = array( 'url' => $url, 'full' => msc_media_url( (array) $ref, 'full' ), 'ref' => $ref );
    }
}
if ( ! $images ) { return; }
?>
<section class="gallery-section" id="gallery" aria-labelledby="gallery-heading">
    <div class="section-heading"><h2 id="gallery-heading">Image gallery</h2></div>
    <div class="image-gallery">
        <?php foreach ( $images as $image ) { ?>
            <a href="<?php echo esc_url( $image['full'] ?: $image['url'] ); ?>">
                <img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $record['data']['title'] . ' promotional image' ); ?>" loading="lazy">
            </a>
            <?php Attribution::collect_media( $image['ref'], $record['data']['title'] ); ?>
        <?php } ?>
    </div>
</section>
