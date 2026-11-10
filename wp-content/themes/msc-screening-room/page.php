<?php
get_header();
while ( have_posts() ) {
    the_post();
    $releases = has_shortcode( get_post_field( 'post_content', get_the_ID() ), \MSC\UpcomingReleases::SHORTCODE );
    echo '<article class="' . ( $releases ? 'release-guide-page' : 'reading-width' ) . '">';
    if ( ! $releases ) { echo '<h1>' . esc_html( get_the_title() ) . '</h1>'; }
    the_content(); echo '</article>';
}
get_footer();
