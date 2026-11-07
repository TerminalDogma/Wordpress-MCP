<?php
$r = $args['record']; $definition = \MSC\EntityCatalog::definitions()[ $r['type'] ];
get_template_part( 'parts/hero', null, array( 'record' => $r, 'label' => $definition[1], 'facts' => array( 'country' => 'Country', 'founded_date' => 'Founded', 'release_date' => 'Launch date' ) ) );
get_template_part( 'parts/story', null, array( 'record' => $r, 'facts' => array( 'headquarters' => 'Headquarters', 'manufacturer' => 'Manufacturer', 'platform_kind' => 'Platform type' ) ) );
$related = \MSC\EntityCatalog::related( $r );
if ( $related ) { echo '<section class="entity-titles"><div class="section-heading"><h2>Related Titles</h2></div><div class="catalog-grid">'; foreach ( $related as $title ) { \MSC\Presentation::card( $title ); } echo '</div></section>'; }
