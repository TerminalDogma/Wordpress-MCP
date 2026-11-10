<?php
$r = $args['record']; $definition = \MSC\EntityCatalog::definitions()[ $r['type'] ];
get_template_part( 'parts/hero', null, array( 'record' => $r, 'label' => $definition[1], 'facts' => array( 'country' => 'Country', 'founded_date' => 'Founded', 'release_date' => 'Launch date' ) ) );
get_template_part( 'parts/story', null, array( 'record' => $r, 'facts' => array( 'headquarters' => 'Headquarters', 'manufacturer' => 'Manufacturer', 'platform_kind' => 'Platform type' ) ) );
if ( 'msc_streaming' === $r['type'] ) { echo '<p><a class="button" href="' . esc_url( \MSC\UpcomingReleases::url( array( 'service' => $r['uuid'] ) ) ) . '">Upcoming releases on ' . esc_html( $r['data']['title'] ) . '</a></p>'; }
if ( 'msc_streaming' === $r['type'] && ! array_key_exists( 'official_website', $r['data'] ) ) { $brand = \MSC\StreamingServices::brands()[ $r['data']['external_ids']['msc_streaming_service'] ?? '' ] ?? null; if ( $brand ) { echo '<p><a href="' . esc_url( $brand[1] ) . '" target="_blank" rel="noopener">Official ' . esc_html( $brand[0] ) . ' website ↗</a></p>'; } }
$related = \MSC\EntityCatalog::related( $r );
if ( $related ) { echo '<section class="entity-titles"><div class="section-heading"><h2>Related Titles</h2></div><div class="catalog-grid">'; foreach ( $related as $title ) { \MSC\Presentation::card( $title ); } echo '</div></section>'; }
