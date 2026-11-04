<?php
add_action( 'after_setup_theme', static function () { add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' ); add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) ); } );
add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'screening-room', get_stylesheet_uri(), array(), '1.4.0' ); } );
function msc_screen_identity(): array { return class_exists( '\MSC\Presentation' ) ? \MSC\Presentation::identity() : array( 'name' => get_bloginfo( 'name' ), 'label' => 'Catalog', 'tagline' => get_bloginfo( 'description' ) ); }
function msc_screen_record(): ?array { $r = function_exists( 'msc_from_post' ) ? msc_from_post( get_the_ID() ) : null; return $r ? \MSC\Presentation::record( $r['id'] ) : null; }
function msc_screen_chips( array $r ): void {
    $taxonomies = get_object_taxonomies( $r['type'] );
    foreach ( $taxonomies as $taxonomy ) {
        if ( ! in_array( $taxonomy, array( 'msc_genre', 'msc_profession' ), true ) ) { continue; }
        $terms = get_the_terms( (int) $r['mapping']['post_id'], $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) { continue; }
        foreach ( $terms as $term ) { $url = get_term_link( $term ); if ( ! is_wp_error( $url ) ) { echo '<a class="chip" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>'; } }
    }
}
function msc_screen_facts( array $r, array $fields ): void {
    echo '<dl class="facts">';
    foreach ( $fields as $key => $label ) {
        $value = $r['data'][ $key ] ?? ''; if ( '' === $value || null === $value || is_array( $value ) ) { continue; }
        if ( str_ends_with( $key, '_date' ) ) { $value = wp_date( 'M j, Y', strtotime( $value . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ); }
        if ( 'runtime' === $key ) { $value .= ' min'; }
        echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
    }
    echo '</dl>';
}
function msc_screen_grid( string $type, int $limit = 6 ): void {
    $posts = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC' ) );
    echo '<div class="catalog-grid">'; $count = 0;
    foreach ( $posts as $post ) { $global = function_exists( 'msc_from_post' ) ? msc_from_post( $post->ID ) : null; $r = $global ? \MSC\Presentation::record( $global['id'] ) : null; if ( $r ) { \MSC\Presentation::card( $r ); ++$count; } }
    if ( ! $count ) { echo '<p class="empty-state">The next story starts here. Published entries will appear in this collection.</p>'; }
    echo '</div>';
}
