<?php
get_header();
while ( have_posts() ) { the_post(); $r = msc_screen_record(); if ( $r ) { get_template_part( 'parts/detail', 'entity', array( 'record' => $r ) ); echo \MSC\Attribution::collect( $r ); } }
get_footer();
