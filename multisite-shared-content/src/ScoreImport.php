<?php
namespace MSC;

/** Games use Wikipedia; Movies/TV retain their existing OMDb score imports. */
final class ScoreImport {
    public static function preview( string $uuid, array $ids ): array {
        if ( ! current_user_can( 'msc_edit_global' ) && ! current_user_can( 'msc_override_local' ) ) { throw new SourceError( 'Score import permission required.', 403 ); }
        return OpenClient::paced( static fn() => self::preview_values( $uuid, $ids ) );
    }
    private static function preview_values( string $uuid, array $ids ): array {
        $r = Plugin::store()->resolved( $uuid ); if ( ! $r || 'active' !== $r['state'] || ! ReviewScores::providers( $r['type'] ) || ! Schema::enabled( $r['type'] ) || ! GamePolicy::accepts( $r ) ) { throw new SourceError( 'This item is not available for score imports on this site.', 403 ); }
        $values = array(); $warnings = array(); $matches = array(); $choices = array(); $candidates = array(); $d = $r['data'];
        if ( 'msc_game' === $r['type'] ) {
        $article = $ids['wikipedia'] ?? $d['score_wikipedia_title'] ?? $d['external_ids']['wikipedia'] ?? '';
        $imdb = trim( (string) ( $ids['imdb'] ?? $d['score_imdb_id'] ?? $d['external_ids']['imdb'] ?? '' ) );
        $qid = $d['external_ids']['wikidata'] ?? '';
        if ( ! $article && is_string( $qid ) && preg_match( '/^Q[1-9][0-9]*$/D', $qid ) && ( current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_create_global' ) ) ) {
            try { $entity = OpenClient::entities( array( $qid ) )[ $qid ] ?? array(); $article = $entity['sitelinks']['enwiki']['title'] ?? ''; if ( ! $imdb ) { $v = OpenSources::claims( $entity, 'P345' )[0] ?? ''; if ( is_string( $v ) ) { $imdb = $v; } } $rt = OpenSources::claims( $entity, 'P1258' )[0] ?? ''; if ( is_string( $rt ) && preg_match( '~^(m|tv)/[a-z0-9_/-]+$~D', $rt ) && 'msc_game' !== $r['type'] ) { $values['rotten_tomatoes_url'] = 'https://www.rottentomatoes.com/' . $rt; } }
            catch ( \Throwable $e ) { /* Article search remains available when an optional identity cannot resolve. */ }
        }
        try {
            if ( $article ) {
                $wiki = WikipediaScores::article( WikipediaScores::title( $article ), $r ); $matches[] = 'Wikipedia: ' . $wiki['title']; $values['score_wikipedia_title'] = $wiki['title']; $choices = $wiki['choices'];
                foreach ( array_intersect( ReviewScores::providers( $r['type'] ), array( 'metacritic', 'opencritic' ) ) as $provider ) { if ( ! empty( $choices[ $provider ] ) ) { $values += $choices[ $provider ][0]['values']; } else { $warnings[] = ReviewScores::PROVIDERS[ $provider ] . ' has no usable score in this Wikipedia article.'; } }
            } else { $candidates = WikipediaScores::search( $d['title'], $r['type'] ); $warnings[] = $candidates ? 'Choose the matching Wikipedia article below, then fetch its scores.' : 'No Wikipedia article found. Enter its article title or URL manually.'; }
        } catch ( \Throwable $e ) { $warnings[] = $e instanceof SourceError ? $e->getMessage() : 'Wikipedia score lookup failed. Please retry.'; }
        } else {
            $imdb = trim( (string) ( $ids['imdb'] ?? $d['score_imdb_id'] ?? $d['external_ids']['imdb'] ?? '' ) ); $qid = $d['external_ids']['wikidata'] ?? '';
            if ( is_string( $qid ) && preg_match( '/^Q[1-9][0-9]*$/D', $qid ) ) {
                try { $entity = OpenClient::entities( array( $qid ) )[ $qid ] ?? array();
                    foreach ( array( 'P345' => 'imdb', 'P1258' => 'rotten_tomatoes', 'P1712' => 'metacritic' ) as $property => $p ) { $v = $entity['claims'][ $property ][0]['mainsnak']['datavalue']['value'] ?? ''; if ( ! is_string( $v ) ) { continue; } if ( 'imdb' === $p && ! $imdb ) { $imdb = $v; } elseif ( 'rotten_tomatoes' === $p && preg_match( '~^(m|tv)/[a-z0-9_/-]+$~D', $v ) ) { $values[ $p . '_url' ] = 'https://www.rottentomatoes.com/' . $v; } elseif ( 'metacritic' === $p && preg_match( '~^(movie|tv|game)/[a-z0-9_/-]+$~D', $v ) ) { $values[ $p . '_url' ] = 'https://www.metacritic.com/' . $v; } }
                } catch ( \Throwable $e ) { $warnings[] = 'Provider page links could not be resolved from Wikidata; enter their title page URLs manually.'; }
            }
            if ( ! $imdb ) { $warnings[] = 'Import the title or enter its IMDb ID to fetch Movie/TV aggregate scores.'; }
            else { try { $data = OmdbClient::details( $imdb ); $expected = array( 'msc_movie' => 'movie', 'msc_show' => 'series', 'msc_episode' => 'episode' )[ $r['type'] ]; if ( $expected !== ( $data['Type'] ?? '' ) ) { throw new SourceError( 'The IMDb ID refers to a different content type.' ); } $matches[] = 'OMDb: ' . sanitize_text_field( $data['Title'] ?? '' ); $values += ReviewScores::omdb( $data ); $values['score_imdb_id'] = $imdb; if ( ! isset( $values['rotten_tomatoes_score'], $values['metacritic_score'] ) ) { $warnings[] = 'OMDb may not have both aggregate scores for this title yet.'; } } catch ( \Throwable $e ) { $warnings[] = $e instanceof OmdbError || $e instanceof SourceError ? $e->getMessage() : 'Movie/TV score lookup failed. Please retry.'; } }
        }
        if ( array_filter( array_keys( $values ), static fn( $key ) => str_ends_with( $key, '_score' ) ) ) { $values['score_updated_at'] = gmdate( 'c' ); }
        foreach ( $choices as &$options ) { foreach ( $options as &$option ) { $option['values'] = Schema::values( $r['type'], $option['values'] ); } unset( $option ); } unset( $options );
        return array( 'values' => Schema::values( $r['type'], $values ), 'warnings' => $warnings, 'matches' => $matches, 'wiki_candidates' => $candidates, 'choices' => $choices );
    }
    public static function boot(): void {
        add_action( 'wp_ajax_msc_score_preview', static function () {
            check_ajax_referer( 'msc_scores_' . get_current_blog_id(), 'nonce' );
            try { if ( ! is_string( $_POST['record'] ?? null ) ) { throw new SourceError( 'Invalid score preview request.' ); } $ids = array(); foreach ( array( 'imdb', 'wikipedia' ) as $p ) { if ( isset( $_POST[ $p ] ) && ! is_string( $_POST[ $p ] ) ) { throw new SourceError( 'Invalid provider ID.' ); } if ( ! empty( $_POST[ $p ] ) ) { $ids[ $p ] = wp_unslash( $_POST[ $p ] ); } } wp_send_json_success( self::preview( wp_unslash( $_POST['record'] ), $ids ) ); }
            catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => $e instanceof SourceError ? $e->getMessage() : 'Score preview failed. Please retry.' ), $e instanceof SourceError ? $e->status : 502 ); }
        } );
        add_action( 'admin_enqueue_scripts', static function () {
            if ( ! current_user_can( 'msc_edit_global' ) && ! current_user_can( 'msc_override_local' ) ) { return; }
            $id = $_GET['record'] ?? ''; if ( ! is_scalar( $id ) || ! $id ) { return; } $r = Plugin::store()->resolved( (string) $id ); if ( ! $r || ! ReviewScores::providers( $r['type'] ) ) { return; }
            wp_enqueue_script( 'msc-score-editor', plugins_url( 'assets/scores-editor.js', MSC_FILE ), array(), MSC_VERSION, true ); wp_localize_script( 'msc-score-editor', 'mscScoreImport', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'record' => $r['uuid'], 'nonce' => wp_create_nonce( 'msc_scores_' . get_current_blog_id() ), 'wikipedia' => 'msc_game' === $r['type'] ) );
        } );
        add_action( 'network_admin_menu', static function () { add_submenu_page( 'msc-network', 'Review Score Sources', 'Review Score Sources', 'manage_network_options', 'msc-score-sources', array( self::class, 'settings' ) ); }, 20 );
    }
    public static function settings(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        echo '<div class="wrap"><h1>Review Score Sources</h1><p>Game Metacritic and OpenCritic values come from Wikipedia without API keys or paid subscriptions. Choose the matching article in the item editor, preview the available platform/measure, then save selected scores.</p><p>Wikipedia sometimes reports OpenCritic as the percentage of critics recommending a game. This is labeled separately from a critic-average score. Missing scores are left unchanged.</p><p>Movies/TV keep their existing OMDb imports for both Rotten Tomatoes and Metacritic. Existing scores and legacy source details are retained; the importer makes no RAWG or RapidAPI requests.</p><p>Wikipedia contributor attribution, the article revision and its license are collected in the site footer. Public pages use stored values and never fetch scores.</p></div>';
    }
}
