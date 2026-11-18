<?php
use MSC\{Plugin, Presentation, Schema, Timelines};

$timelines = $args['timelines'] ?? null;
if ( null === $timelines ) {
    if ( ! is_singular() || ! Schema::enabled( Timelines::TYPE ) ) { return; }
    $post = get_queried_object();
    if ( ! $post instanceof WP_Post || $post->post_password || in_array( $post->post_type, array( Timelines::TYPE, 'msc_movie', 'msc_show' ), true ) || ! Schema::enabled( $post->post_type ) ) { return; }
    $global = Plugin::store()->by_post( $post->ID );
    $item = $global ? Presentation::record( $global['id'] ) : null;
    $timelines = $item ? Timelines::related( $item ) : array();
}
if ( ! $timelines ) { return; }
?>
<section class="entry-timelines" aria-labelledby="entry-timelines-heading">
    <h2 id="entry-timelines-heading">Story timelines</h2>
    <ul>
        <?php foreach ( $timelines as $timeline ) {
            $match = $timeline['timeline_matches'][0] ?? null;
            $anchor = null === $match ? '' : '#timeline-entry-' . ( $match + 1 );
            ?>
            <li><a href="<?php echo esc_url( Presentation::url( $timeline ) . $anchor ); ?>">
                <?php echo esc_html( $timeline['data']['title'] ); ?>
                <span aria-hidden="true">→</span>
            </a></li>
        <?php } ?>
    </ul>
</section>
