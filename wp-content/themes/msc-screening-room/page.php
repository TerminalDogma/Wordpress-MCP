<?php get_header(); while ( have_posts() ) { the_post(); echo '<article class="reading-width"><h1>' . esc_html( get_the_title() ) . '</h1>'; the_content(); echo '</article>'; } get_footer();
