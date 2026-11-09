<?php $r = $args['record']; $d = $r['data']; $parent = ! empty( $d['parent_show'] ) ? \MSC\Presentation::record( $d['parent_show'] ) : null;
get_template_part( 'parts/hero', null, array( 'record' => $r, 'label' => 'Season ' . ( $d['season_number'] ?? '?' ) . ' · Episode ' . ( $d['episode_number'] ?? '?' ), 'facts' => array( 'release_date' => 'Air date', 'runtime' => 'Runtime', 'air_time' => 'Air time', 'time_zone' => 'Time zone' ) ) );
if ( $parent ) { echo '<a class="parent-show" href="' . esc_url( \MSC\Presentation::url( $parent ) ) . '"><span class="eyebrow">TV Show</span><strong>' . esc_html( $parent['data']['title'] ) . '</strong><span>View TV Show <span class="arrow" aria-hidden="true"></span></span></a>'; }
get_template_part( 'parts/story', null, array( 'record' => $r, 'facts' => array( 'network' => 'Network', 'streaming_service' => 'Streaming service', 'overall_episode_number' => 'Overall episode', 'production_code' => 'Production code' ) ) ); \MSC\Presentation::credits( $r );

\MSC\ReviewScores::render( $r );
