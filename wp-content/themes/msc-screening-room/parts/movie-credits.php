<?php
use MSC\Presentation;
$record = $args['record'];
ob_start();
Presentation::credits( $record, true, true );
$markup = trim( (string) ob_get_clean() );
$legacy = array();
foreach ( array( 'director' => array( 'directors', 'Director' ), 'writers' => array( 'screenwriters', 'Writing credits' ), 'cast' => array( 'actors', 'Cast notes' ) ) as $key => list( $role, $label ) ) {
    if ( empty( $args['credits'][ $role ] ) && ! empty( $record['data'][ $key ] ) ) { $legacy[ $label ] = $record['data'][ $key ]; }
}
if ( ! $markup && ! $legacy ) { return; }
?>
<section class="movie-section" id="credits">
    <h2>Cast and crew</h2>
    <?php echo $markup; ?>
    <?php foreach ( $legacy as $label => $value ) { ?><div class="title-legacy-credit"><h3><?php echo esc_html( $label ); ?></h3><div class="catalog-description movie-prose"><?php echo wp_kses_post( wpautop( $value ) ); ?></div></div><?php } ?>
</section>
