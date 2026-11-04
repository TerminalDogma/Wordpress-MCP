<?php
get_header();
while ( have_posts() ) { the_post(); $r = msc_screen_record(); if ( $r ) { get_template_part( 'parts/detail', 'movie', array( 'record' => $r ) ); } else { echo '<article class="reading-width"><h1>' . esc_html( get_the_title() ) . '</h1>'; the_content(); echo '</article>'; } }
get_footer();
