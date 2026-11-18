<?php
use MSC\{Plugin, Presentation, Schema, Timelines};

if ( ! is_singular() || ! Schema::enabled( Timelines::TYPE ) ) { return; }
$post = get_queried_object();
if ( ! $post instanceof WP_Post || $post->post_password || $post->post_type === Timelines::TYPE || ! Schema::enabled( $post->post_type ) ) { return; }
$global = Plugin::store()->by_post( $post->ID );
$item = $global ? Presentation::record( $global['id'] ) : null;
if ( ! $item ) { return; }
$timelines = Timelines::related( $item );
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
