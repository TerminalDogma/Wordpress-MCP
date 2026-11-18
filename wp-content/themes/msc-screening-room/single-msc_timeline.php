<?php
use MSC\{Attribution, Media, Presentation, Timelines};

get_header();
while ( have_posts() ) {
    the_post();
    $record = msc_screen_record();
    if ( ! $record ) {
        echo '<article class="reading-width"><h1>' . esc_html( get_the_title() ) . '</h1>';
        the_content();
        echo '</article>';
        continue;
    }
    get_template_part( 'parts/detail', 'timeline', array( 'record' => $record ) );
    Attribution::collect( $record );
}
get_footer();
