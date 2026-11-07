<?php
if ( class_exists( '\MSC\CatalogArchive' ) && \MSC\CatalogArchive::current() ) { get_header(); get_template_part( 'parts/catalog-archive' ); get_footer(); }
else { require __DIR__ . '/index.php'; }
