<?php $r = $args['record']; ?>
<section id="episodes" class="episode-guide" data-show-uuid="<?php echo esc_attr( $r['uuid'] ); ?>"><div class="section-heading"><h2>Episodes</h2></div><p class="episode-load-status" role="status" aria-live="polite"></p><div class="episode-guide-content" aria-busy="false">
<?php $guide = \MSC\Presentation::season_page( $r['uuid'], $_GET['season'] ?? null, $_GET['episode_page'] ?? null );
if ( ! $guide['seasons'] ) { echo '<p class="empty-state">No published episodes are linked to this TV Show.</p>'; }
else {
    echo '<nav class="season-pagination" aria-label="Episode seasons">';
    foreach ( $guide['seasons'] as $season ) { $active = $season === $guide['season']; echo '<a href="' . esc_url( add_query_arg( 'season', $season, \MSC\Presentation::url( $r ) ) . '#episodes' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( 'unknown' === $season ? 'Season not set' : 'Season ' . $season ) . '</a>'; }
    echo '</nav><h3 class="season-heading" tabindex="-1">' . esc_html( 'unknown' === $guide['season'] ? 'Season not set' : 'Season ' . $guide['season'] ) . '</h3><ol class="episode-list">';
    foreach ( $guide['episodes'] as $episode ) { echo '<li><a href="' . esc_url( \MSC\Presentation::url( $episode ) ) . '"><span class="episode-number">' . esc_html( isset( $episode['data']['episode_number'] ) && '' !== $episode['data']['episode_number'] ? str_pad( (string) $episode['data']['episode_number'], 2, '0', STR_PAD_LEFT ) : '—' ) . '</span><span><strong>' . esc_html( $episode['data']['title'] ) . '</strong></span><time>' . esc_html( $episode['data']['release_date'] ?? '' ) . '</time><span aria-hidden="true"><span class="arrow" aria-hidden="true"></span></span></a>' . \MSC\Attribution::collect( $episode, array( 'title', 'season_number', 'episode_number', 'release_date' ) ) . '</li>'; }
    echo '</ol>';
    if ( $guide['pages'] > 1 ) { echo '<nav class="episode-pagination" aria-label="Episode pages">'; foreach ( array( $guide['page'] - 1 => 'Previous episodes', $guide['page'] + 1 => 'Next episodes' ) as $number => $label ) { if ( $number >= 1 && $number <= $guide['pages'] ) { echo '<a href="' . esc_url( add_query_arg( array( 'season' => $guide['season'], 'episode_page' => $number ), \MSC\Presentation::url( $r ) ) . '#episodes' ) . '">' . esc_html( $label ) . '</a>'; } } echo '<span>' . esc_html( 'Page ' . $guide['page'] . ' of ' . $guide['pages'] ) . '</span></nav>'; }
} ?>
</div></section>
