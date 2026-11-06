<?php $identity = msc_screen_identity(); $key = get_option( 'msc_site_identity', 'a' ); ?>
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?> data-identity="<?php echo esc_attr( $key ); ?>"><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<div class="site-ribbon"><div class="container"><span><span class="status-dot"></span> <?php echo esc_html( $identity['label'] ); ?> <span class="ribbon-note">/ Shared content demo</span></span><nav aria-label="Test sites">
<?php if ( is_multisite() ) { foreach ( get_sites( array( 'number' => 100 ) ) as $site ) { $letter = array( '/site-a/' => 'A', '/site-b/' => 'B', '/site-c/' => 'C' )[ $site->path ] ?? ''; if ( $letter ) { echo '<a href="' . esc_url( get_home_url( (int) $site->blog_id, '/' ) ) . '"' . ( (int) $site->blog_id === get_current_blog_id() ? ' aria-current="page"' : '' ) . '>Site ' . esc_html( $letter ) . '</a>'; } } } ?>
</nav></div></div>
<header class="site-header container"><a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $identity['name'] ); ?></a><nav class="primary-nav" aria-label="Main navigation">
<?php foreach ( array( 'msc_movie' => 'Movies', 'msc_show' => 'TV Shows', 'msc_episode' => 'Episodes', 'msc_person' => 'People' ) as $type => $label ) { $url = get_post_type_archive_link( $type ); if ( $url ) { echo '<a href="' . esc_url( $url ) . '"' . ( is_post_type_archive( $type ) || is_singular( $type ) ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; } } ?>
</nav><form role="search" class="header-search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"><label class="screen-reader-text" for="catalog-search">Search the catalog</label><input id="catalog-search" type="search" name="s" placeholder="Search catalog" value="<?php echo get_search_query(); ?>"><button type="submit" aria-label="Search"><span class="arrow" aria-hidden="true"></span></button></form></header>
<main id="main" class="container">
