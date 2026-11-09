<?php
function msc_archive_arrow(): string { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 12h16M13 5l7 7-7 7"/></svg>'; }
function msc_archive_date( string $date ): string { return $date ? wp_date( 'M j, Y', strtotime( $date . ' 12:00 UTC' ), new DateTimeZone( 'UTC' ) ) : ''; }
function msc_archive_meta( array $r ): array {
    $d = $r['data']; $facts = array(); $tags = array(); $kicker = ''; $date = $r['catalog_date'];
    if ( in_array( $r['type'], array( 'msc_movie', 'msc_show', 'msc_game' ), true ) ) { $kicker = implode( ' · ', array_slice( (array) ( $d['genres'] ?? array() ), 0, 2 ) ); }
    if ( $date ) { $facts[] = msc_archive_date( $date ); }
    if ( in_array( $r['type'], array( 'msc_movie', 'msc_episode' ), true ) && ! empty( $d['runtime'] ) ) { $facts[] = $d['runtime'] . ' min'; }
    if ( 'msc_movie' === $r['type'] && ! empty( $d['content_rating'] ) ) { $tags[] = $d['content_rating']; }
    if ( 'msc_show' === $r['type'] ) { if ( ! empty( $d['number_of_seasons'] ) ) { $facts[] = $d['number_of_seasons'] . ( 1 === (int) $d['number_of_seasons'] ? ' season' : ' seasons' ); } $tags = array_filter( array( ( $d['network'] ?? '' ) ?: ( $d['streaming_service'] ?? '' ), $d['status'] ?? '' ) ); }
    if ( 'msc_game' === $r['type'] ) { $tags = (array) ( $d['platforms'] ?? array() ); }
    if ( 'msc_person' === $r['type'] ) { $kicker = implode( ' · ', array_slice( (array) ( $d['professions'] ?? array() ), 0, 2 ) ); $facts = array_filter( array( $d['country'] ?? '' ) ); }
    if ( 'msc_episode' === $r['type'] ) { $parent = ! empty( $d['parent_show'] ) ? \MSC\CatalogArchive::related( $d['parent_show'] ) : null; $kicker = $parent['data']['title'] ?? 'Episode'; if ( isset( $d['season_number'] ) && '' !== $d['season_number'] ) { $tags[] = 'Season ' . $d['season_number']; } if ( isset( $d['episode_number'] ) && '' !== $d['episode_number'] ) { $tags[] = 'Episode ' . $d['episode_number']; } }
    if ( isset( \MSC\EntityCatalog::definitions()[ $r['type'] ] ) ) { $kicker = \MSC\EntityCatalog::definitions()[ $r['type'] ][1]; $facts = array_filter( array( $date ? msc_archive_date( $date ) : '', ( $d['headquarters'] ?? '' ) ?: ( $d['country'] ?? '' ), ! empty( $d['founded_date'] ) ? 'Founded ' . substr( $d['founded_date'], 0, 4 ) : '', $d['manufacturer'] ?? '' ) ); $tags = array_filter( array( $d['platform_kind'] ?? '' ) ); }
    if ( ! $kicker ) { $kicker = array( 'msc_movie' => 'Movie', 'msc_show' => 'TV Show', 'msc_game' => 'Video Game', 'msc_person' => 'Person' )[ $r['type'] ] ?? 'Profile'; }
    return array( 'kicker' => $kicker, 'facts' => array_values( $facts ), 'tags' => array_values( array_unique( $tags ) ), 'date' => $date );
}
function msc_archive_card( array $r ): void {
    $meta = msc_archive_meta( $r ); $url = \MSC\Presentation::url( $r );
    echo '<article class="catalog-card archive-card type-' . esc_attr( $r['type'] ) . '"><a class="archive-card-link" href="' . esc_url( $url ) . '">';
    if ( 'msc_episode' !== $r['type'] ) { echo '<div class="archive-card-media">' . \MSC\Presentation::image( $r, 'card-art', 'medium_large' ) . \MSC\ReviewScores::badge( $r ); if ( $meta['date'] && in_array( $r['type'], array( 'msc_movie', 'msc_game' ), true ) ) { echo '<span class="archive-year">' . esc_html( substr( $meta['date'], 0, 4 ) ) . '</span>'; } echo '</div>'; }
    echo '<div class="card-copy"><span class="archive-card-kicker">' . esc_html( $meta['kicker'] ) . '</span><h2 class="archive-card-title">' . esc_html( $r['data']['title'] ?? '(Untitled)' ) . '</h2>';
    if ( $meta['facts'] ) { echo '<ul class="archive-card-facts">'; foreach ( $meta['facts'] as $fact ) { echo '<li>' . esc_html( $fact ) . '</li>'; } echo '</ul>'; }
    if ( $meta['tags'] ) { echo '<ul class="archive-card-tags" aria-label="' . esc_attr( implode( ', ', $meta['tags'] ) ) . '">'; foreach ( array_slice( $meta['tags'], 0, 2 ) as $tag ) { echo '<li>' . esc_html( $tag ) . '</li>'; } if ( count( $meta['tags'] ) > 2 ) { echo '<li>+' . ( count( $meta['tags'] ) - 2 ) . '</li>'; } echo '</ul>'; }
    if ( isset( \MSC\EntityCatalog::definitions()[ $r['type'] ] ) ) { echo '<span class="archive-profile-link">View profile ' . msc_archive_arrow() . '</span>'; }
    echo '</div></a></article>';
    echo \MSC\Attribution::collect( $r, array( 'title', 'genres', 'release_date', 'premiere_date', 'runtime', 'content_rating', 'number_of_seasons', 'network', 'streaming_service', 'status', 'platforms', 'professions', 'country', 'headquarters', 'founded_date', 'manufacturer', 'platform_kind', 'season_number', 'episode_number' ) ); if ( 'msc_episode' !== $r['type'] ) { echo \MSC\Attribution::collect_image( $r ); }
}
