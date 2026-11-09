<?php
namespace MSC;

final class ReviewScores {
    public const PROVIDERS = array( 'rotten_tomatoes' => 'Rotten Tomatoes®', 'metacritic' => 'Metacritic', 'opencritic' => 'OpenCritic' );
    public static function providers( string $type ): array { return 'msc_game' === $type ? array( 'metacritic', 'opencritic' ) : ( in_array( $type, array( 'msc_movie', 'msc_show', 'msc_episode' ), true ) ? array( 'rotten_tomatoes', 'metacritic' ) : array() ); }
    public static function fields( string $type ): array {
        $fields = array(); foreach ( self::providers( $type ) as $p ) {
            $fields[ $p . '_score' ] = array( 'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 0.1, 'label' => self::PROVIDERS[ $p ] . ' critic score / 100' );
            $fields[ $p . '_url' ] = array( 'type' => 'url', 'format' => 'score_url', 'provider' => $p, 'label' => self::PROVIDERS[ $p ] . ' title page URL' );
        }
        if ( ! $fields ) { return array(); }
        $fields['score_updated_at'] = array( 'type' => 'text', 'label' => 'Aggregate scores retrieved at' );
        if ( 'msc_game' === $type ) {
            $fields['score_wikipedia_title'] = array( 'type' => 'text', 'format' => 'wikipedia_title', 'label' => 'Wikipedia article title or URL' );
            $fields['metacritic_data_source'] = array( 'type' => 'url', 'format' => 'score_source_url', 'label' => 'Metacritic Wikipedia source revision' );
            $fields['metacritic_platform'] = array( 'type' => 'text', 'label' => 'Metacritic platform / edition' );
            $fields['opencritic_data_source'] = array( 'type' => 'url', 'format' => 'score_source_url', 'label' => 'OpenCritic Wikipedia source revision' );
            $fields['opencritic_metric'] = array( 'type' => 'select', 'options' => array( 'average', 'recommended' ), 'label' => 'OpenCritic measure (average / critics recommended)' );
            $fields['opencritic_score']['label'] = 'OpenCritic score or critics recommended (0–100)';
        } else { $fields['score_imdb_id'] = array( 'type' => 'text', 'label' => 'IMDb title ID for aggregate scores' ); }
        return $fields;
    }
    public static function value( $v ): ?float { return is_scalar( $v ) && is_numeric( $v ) && is_finite( (float) $v ) && $v >= 0 && $v <= 100 ? round( (float) $v, 1 ) : null; }
    public static function omdb( array $data ): array {
        $values = array(); foreach ( (array) ( $data['Ratings'] ?? array() ) as $row ) {
            if ( ! is_array( $row ) ) { continue; } $source = is_string( $row['Source'] ?? null ) ? $row['Source'] : ''; $p = array( 'Rotten Tomatoes' => 'rotten_tomatoes', 'Metacritic' => 'metacritic' )[ $source ] ?? '';
            if ( $p && preg_match( '/^([0-9]+(?:\.[0-9]+)?)(?:%|\/100)$/D', ( is_scalar( $row['Value'] ?? null ) ? (string) $row['Value'] : '' ), $m ) && null !== self::value( $m[1] ) ) { $values[ $p . '_score' ] = self::value( $m[1] ); }
        }
        if ( ! isset( $values['metacritic_score'] ) && null !== self::value( $data['Metascore'] ?? '' ) ) { $values['metacritic_score'] = self::value( $data['Metascore'] ); }
        return $values;
    }
    public static function source_url( $url ): string {
        if ( ! is_string( $url ) ) { return ''; } $p = wp_parse_url( $url );
        if ( ! $p || 'https' !== ( $p['scheme'] ?? '' ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['port'] ) ) { return ''; }
        if ( 'en.wikipedia.org' === ( $p['host'] ?? '' ) && ( str_starts_with( $p['path'] ?? '', '/wiki/' ) || '/w/index.php' === ( $p['path'] ?? '' ) ) ) { return esc_url_raw( $url ); }
        return preg_match( '~^https://(?:www\.)?rawg\.io/games/[a-z0-9-]+/?$~D', $url ) ? $url : '';
    }
    public static function provider_url( string $p, $url ): string {
        if ( ! is_string( $url ) ) { return ''; } $parts = wp_parse_url( $url ); $hosts = array( 'rotten_tomatoes' => array( 'rottentomatoes.com', 'www.rottentomatoes.com' ), 'metacritic' => array( 'metacritic.com', 'www.metacritic.com' ), 'opencritic' => array( 'opencritic.com', 'www.opencritic.com' ) );
        return $parts && 'https' === ( $parts['scheme'] ?? '' ) && in_array( strtolower( $parts['host'] ?? '' ), $hosts[ $p ] ?? array(), true ) && empty( $parts['user'] ) && empty( $parts['pass'] ) && empty( $parts['port'] ) ? esc_url_raw( $url ) : '';
    }
    public static function rows( array $r ): array {
        $rows = array(); $providers = self::providers( $r['type'] ); if ( ! $providers || ! Schema::enabled( $r['type'] ) || ! GamePolicy::accepts( $r ) || 'active' !== ( $r['state'] ?? 'active' ) ) { return $rows; }
        $own = Reviews::own( $r ); if ( $own ) { $rows[] = $own; }
        $legacy = self::omdb( (array) ( $r['data']['additional_metadata']['omdb'] ?? array() ) );
        foreach ( $providers as $p ) {
            $key = $p . '_score'; $score = self::value( array_key_exists( $key, $r['data'] ) ? $r['data'][ $key ] : ( $legacy[ $key ] ?? '' ) ); if ( null === $score ) { continue; }
            $url = self::provider_url( $p, $r['data'][ $p . '_url' ] ?? '' ); $wiki = self::source_url( $r['data'][ $p . '_data_source' ] ?? '' ); if ( ! $url && str_starts_with( $wiki, 'https://en.wikipedia.org/' ) ) { $url = $wiki; }
            $rows[] = array( 'provider' => $p, 'name' => self::PROVIDERS[ $p ], 'score' => $score, 'max' => 100, 'url' => $url, 'award' => '', 'metric' => 'opencritic' === $p && 'recommended' === ( $r['data']['opencritic_metric'] ?? '' ) ? 'recommended' : 'average', 'platform' => 'metacritic' === $p ? ( $r['data']['metacritic_platform'] ?? '' ) : '' );
        }
        return $rows;
    }
    public static function badge( array $r ): string { $own = Reviews::own( $r ); return $own ? self::badge_row( $own ) : ''; }
    public static function badge_row( array $row ): string {
        if ( 'editorial' !== ( $row['provider'] ?? '' ) ) { return ''; }
        $award = $row['award']; $suffix = ( 'rotten_tomatoes' === $row['provider'] || 'recommended' === ( $row['metric'] ?? '' ) ) ? '%' : '/' . $row['max'];
        return '<span class="item-score-badge' . ( $award ? ' award-' . esc_attr( $award ) : '' ) . '"><span class="score-source">' . esc_html( $row['name'] ) . '</span><strong>' . esc_html( Reviews::number( $row['score'] ) ) . '<small>' . esc_html( $suffix ) . '</small></strong>' . ( $award ? '<span class="score-award">' . esc_html( ucfirst( $award ) . ' recommendation' ) . '</span>' : '' ) . '</span>';
    }
    public static function render( array $r ): void {
        $rows = self::rows( $r ); if ( ! $rows ) { return; }
        echo '<section class="review-scores" id="review-scores"><div class="section-heading"><h2>Review Scores</h2></div><div class="review-score-grid">';
        foreach ( $rows as $row ) {
            echo '<article class="review-score-card' . ( 'editorial' === $row['provider'] ? ' editorial-score' : '' ) . ( $row['award'] ? ' award-' . esc_attr( $row['award'] ) : '' ) . '"><p class="eyebrow">' . ( 'editorial' === $row['provider'] ? 'Our review' : 'Critic aggregate' ) . '</p><h3>' . esc_html( $row['name'] ) . '</h3><p class="review-score-value">' . esc_html( Reviews::number( $row['score'] ) ) . '<small>' . ( ( 'rotten_tomatoes' === $row['provider'] || 'recommended' === ( $row['metric'] ?? '' ) ) ? '%' : '/' . (int) $row['max'] ) . '</small></p>';
            if ( ! empty( $row['platform'] ) || 'recommended' === ( $row['metric'] ?? '' ) ) { echo '<p class="score-context">' . esc_html( 'recommended' === ( $row['metric'] ?? '' ) ? 'Critics recommend' : $row['platform'] ) . '</p>'; }
            if ( $row['award'] ) { echo '<p class="review-award-label">' . esc_html( ucfirst( $row['award'] ) . ' recommendation' ) . '</p>'; }
            if ( $row['url'] ) { echo '<a class="text-link" href="' . esc_url( $row['url'] ) . '">' . ( 'editorial' === $row['provider'] ? 'Read our review' : 'View source score' ) . '</a>'; }
            echo '</article>';
        }
        echo '</div></section>';
        self::sources( $r, $rows );
    }
    private static function sources( array $r, array $rows ): void {
        foreach ( $rows as $row ) { if ( 'editorial' !== $row['provider'] && $row['url'] ) { Attribution::collect_html( $r['data']['title'] . ' — ' . $row['name'], '<a href="' . esc_url( $row['url'] ) . '" rel="license noopener">' . esc_html( $row['name'] ) . '</a>' ); } }
        foreach ( array( 'metacritic', 'opencritic' ) as $p ) { $wiki = self::source_url( $r['data'][ $p . '_data_source' ] ?? '' ); if ( $wiki && str_starts_with( $wiki, 'https://en.wikipedia.org/' ) && array_filter( $rows, static fn( $row ) => $p === $row['provider'] ) ) { Attribution::collect_html( $r['data']['title'] . ' — ' . self::PROVIDERS[ $p ] . ' via Wikipedia', Attribution::source_html( OpenSources::source( 'wikipedia', $wiki ) ) ); } }
        $source = $r['data']['metacritic_data_source'] ?? ''; if ( is_string( $source ) && preg_match( '~^https://(?:www\.)?rawg\.io/games/[a-z0-9-]+/?$~D', $source ) ) { Attribution::collect_html( $r['data']['title'] . ' — Metacritic data provider', '<a href="' . esc_url( $source ) . '" rel="license noopener">Metacritic game data via RAWG</a>' ); }
    }
}
