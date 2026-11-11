<?php
require_once __DIR__ . '/inc/catalog-archive.php';
add_action( 'wp_enqueue_scripts', static function () { if ( is_singular( array( 'msc_franchise', 'msc_movie', 'msc_show' ) ) ) { wp_enqueue_style( 'msc-franchises', get_template_directory_uri() . '/assets/franchises.css', array( 'network-presentation' ), MSC_VERSION ); } }, 30 );
add_action( 'wp_enqueue_scripts', static function () { if ( is_singular( \MSC\Comics::TYPES ) ) { wp_enqueue_style( 'msc-comics', get_template_directory_uri() . '/assets/comics.css', array( 'network-presentation' ), MSC_VERSION ); wp_enqueue_script( 'msc-comics', get_template_directory_uri() . '/assets/comics.js', array(), MSC_VERSION, true ); } }, 30 );
add_action( 'after_setup_theme', static function () { add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' ); add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) ); } );
add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'screening-room', get_stylesheet_uri(), array(), '2.7.3' ); wp_enqueue_style( 'network-presentation', get_template_directory_uri() . '/assets/network.css', array( 'screening-room' ), '2.7.3' ); wp_enqueue_script( 'network-presentation', get_template_directory_uri() . '/assets/network.js', array(), '2.7.3', true ); } );
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
        if ( in_array( $key, \MSC\EntityCatalog::legacy_fields( $r['type'] ), true ) ) { continue; }
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
    if ( ! $count ) { echo '<p class="empty-state">No published entries in this section.</p>'; }
    echo '</div>';
}
function msc_network_switcher(): void {
    if ( ! is_multisite() ) { return; }
    foreach ( get_sites( array( 'number' => 100 ) ) as $site ) { $key = \MSC\NetworkSites::key( $site->path ); if ( $key ) { echo '<a href="' . esc_url( get_home_url( (int) $site->blog_id, '/' ) ) . '"' . ( (int) $site->blog_id === get_current_blog_id() ? ' aria-current="page"' : '' ) . '>Site ' . esc_html( strtoupper( $key ) ) . '</a>'; } }
}
function msc_news_category_url( string $name ): string { $term = get_term_by( 'slug', sanitize_title( $name ), 'category' ); return $term ? (string) get_category_link( $term->term_id ) : add_query_arg( 's', $name, home_url( '/' ) ); }
function msc_news_art( WP_Post $post, string $class = '' ): void { $art = min( 7, max( 0, (int) get_post_meta( $post->ID, '_msc_demo_art', true ) ) ); echo '<span class="news-art art-' . $art . ' ' . esc_attr( $class ) . '" role="img" aria-label="Original artwork for ' . esc_attr( $post->post_title ) . '"></span>'; }
function msc_news_card( WP_Post $post, string $class = '', bool $score = false ): void {
    $category = get_the_category( $post->ID ); $label = get_post_meta( $post->ID, '_msc_release_page_key', true ) ? 'Release guide' : ( $category[0]->name ?? 'News' );
    echo '<article class="news-card ' . esc_attr( $class ) . '"><a href="' . esc_url( get_permalink( $post ) ) . '"><div class="news-art-wrap">';
    $art = min( 7, max( 0, (int) get_post_meta( $post->ID, '_msc_demo_art', true ) ) ); echo '<span class="news-art art-' . $art . '" role="img" aria-label="Original artwork for ' . esc_attr( $post->post_title ) . '">'; if ( $score ) { echo '<span class="review-score" aria-label="Demo review score ' . esc_attr( get_post_meta( $post->ID, '_msc_review_score', true ) ?: '8' ) . ' out of 10">' . esc_html( get_post_meta( $post->ID, '_msc_review_score', true ) ?: '8' ) . '</span>'; } echo '</span></div><div class="news-copy"><span class="news-category">' . esc_html( $label ) . '</span><h3>' . esc_html( $post->post_title ) . '</h3>';
    if ( 'list-card' === $class ) { echo '<p class="news-excerpt">' . esc_html( wp_trim_words( $post->post_excerpt, 22 ) ) . '</p>'; }
    echo '<p class="news-byline">Network demo editorial · ' . esc_html( get_the_date( 'M j', $post ) ) . '</p></div></a></article>';
}

function msc_screen_overview( array $record ): string {
    $data = $record['data']; $content = trim( (string) ( $data['content'] ?? '' ) );
    $field = '' !== $content ? 'content' : 'excerpt'; $value = (string) ( $data[ $field ] ?? '' );
    $source = $data['additional_metadata']['open_import']['fields'][ $field ] ?? array();
    if ( 'wikipedia' === ( $source['source']['provider'] ?? '' ) && ( $source['hash'] ?? '' ) === \MSC\OpenImport::hash( $value ) ) {
        // Older imports wrapped Wikipedia's paragraph newlines in one <p> with <br>s.
        $value = preg_replace( '~<br\s*/?>\s*~i', "</p>\n<p>", $value );
    }
    return wp_kses_post( apply_filters( 'the_content', $value ) );
}
add_action( 'wp_enqueue_scripts', static function () { if ( is_singular( 'msc_show' ) ) { wp_enqueue_script( 'msc-episode-seasons', get_template_directory_uri() . '/assets/episodes.js', array(), '2.7.3', true ); } } );
add_action( 'wp_enqueue_scripts', static function () { if ( \MSC\CatalogArchive::current() ) { wp_enqueue_style( 'msc-catalog-archives', get_template_directory_uri() . '/assets/archives.css', array( 'network-presentation' ), '2.7.3' ); wp_enqueue_script( 'msc-catalog-archives', get_template_directory_uri() . '/assets/archives.js', array(), '2.7.3', true ); } } );

add_action( 'wp_enqueue_scripts', static function () { if ( is_singular( array( 'msc_movie', 'msc_show', 'msc_game' ) ) ) { wp_enqueue_script( 'msc-trailers', get_template_directory_uri() . '/assets/trailers.js', array(), '2.7.3', true ); } } );

add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'msc-reviews', get_template_directory_uri() . '/assets/reviews.css', array( 'network-presentation' ), '2.7.3' ); } );
