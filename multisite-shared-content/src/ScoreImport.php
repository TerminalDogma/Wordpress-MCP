<?php
namespace MSC;

/** Explicit editor previews only. Public pages never call external score APIs. */
final class ScoreImport {
    public static function key( string $provider ): string { $constant = 'MSC_' . strtoupper( $provider ) . '_API_KEY'; return trim( (string) ( defined( $constant ) ? constant( $constant ) : ( getenv( strtoupper( $provider ) . '_API_KEY' ) ?: get_network_option( get_current_network_id(), 'msc_score_key_' . $provider, '' ) ) ) ); }
    public static function managed( string $p ): bool { return defined( 'MSC_' . strtoupper( $p ) . '_API_KEY' ) || (bool) getenv( strtoupper( $p ) . '_API_KEY' ); }
    public static function http( string $provider, string $path ): array {
        if ( ! current_user_can( 'msc_edit_global' ) && ! current_user_can( 'msc_override_local' ) ) { throw new SourceError( 'Score import permission required.', 403 ); }
        $key = self::key( $provider ); if ( ! $key ) { throw new SourceError( ucfirst( $provider ) . ' needs an API key in Review Score Sources.', 503 ); }
        if ( ! in_array( $provider, array( 'rawg', 'opencritic' ), true ) || ! preg_match( '~^/game(?:s)?/[a-z0-9-]{1,100}$~D', $path ) ) { throw new SourceError( 'Invalid game score lookup.' ); }
        $fingerprint = hash( 'sha256', $provider . $key ); $cache = 'msc_score_' . hash( 'sha256', $fingerprint . $path ); $cached = get_site_transient( $cache ); if ( is_array( $cached ) ) { return $cached; }
        if ( get_site_transient( 'msc_score_backoff_' . $fingerprint ) ) { throw new SourceError( ucfirst( $provider ) . ' is rate limited. Please retry later.', 429 ); }
        Database::lock( 'score-rate:' . get_current_user_id(), static function () {
            $name = 'msc_score_rate_' . get_current_user_id(); $state = (array) get_site_transient( $name ); $minute = (int) floor( time() / 60 ); $count = ( $state['minute'] ?? 0 ) === $minute ? (int) ( $state['count'] ?? 0 ) : 0;
            if ( $count >= 20 ) { throw new SourceError( 'Wait a minute before requesting more aggregate scores.', 429 ); } set_site_transient( $name, array( 'minute' => $minute, 'count' => $count + 1 ), 65 );
        } );
        $headers = array( 'Accept' => 'application/json' );
        if ( 'rawg' === $provider ) { $url = add_query_arg( 'key', $key, 'https://api.rawg.io/api' . $path ); }
        else { $url = 'https://opencritic-api.p.rapidapi.com' . $path; $headers['X-RapidAPI-Key'] = $key; $headers['X-RapidAPI-Host'] = 'opencritic-api.p.rapidapi.com'; }
        $response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => MB_IN_BYTES, 'headers' => $headers ) );
        if ( is_wp_error( $response ) ) { throw new SourceError( ucfirst( $provider ) . ' could not be reached.', 502 ); }
        $status = wp_remote_retrieve_response_code( $response ); if ( 429 === $status ) { $seconds = max( 5, min( 300, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ?: 30 ) ); set_site_transient( 'msc_score_backoff_' . $fingerprint, true, $seconds ); throw new SourceError( ucfirst( $provider ) . ' quota reached. Please retry later.', 429 ); }
        if ( 200 !== $status ) { throw new SourceError( ucfirst( $provider ) . ( in_array( $status, array( 401, 403 ), true ) ? ' access was rejected. Check the key and API subscription.' : ' did not return this game. Check the ID or try later.' ), 502 ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true ); if ( ! is_array( $data ) || array_is_list( $data ) || ! is_string( $data['name'] ?? null ) ) { throw new SourceError( ucfirst( $provider ) . ' returned an unreadable game response.', 502 ); }
        $clean = array_intersect_key( $data, array_flip( array( 'name', 'id', 'slug', 'metacritic', 'metacritic_url', 'topCriticScore', 'numReviews' ) ) ); set_site_transient( $cache, $clean, 6 * HOUR_IN_SECONDS ); return $clean;
    }
    public static function preview( string $uuid, array $ids ): array {
        if ( ! current_user_can( 'msc_edit_global' ) && ! current_user_can( 'msc_override_local' ) ) { throw new SourceError( 'Score import permission required.', 403 ); }
        $r = Plugin::store()->resolved( $uuid ); if ( ! $r || 'active' !== $r['state'] || ! ReviewScores::providers( $r['type'] ) || ! Schema::enabled( $r['type'] ) || ! GamePolicy::accepts( $r ) ) { throw new SourceError( 'This item is not available for score imports on this site.', 403 ); }
        $values = array(); $warnings = array(); $matches = array(); $d = $r['data'];
        if ( 'msc_game' === $r['type'] ) {
            foreach ( array( 'rawg', 'opencritic' ) as $p ) {
                $id = trim( (string) ( $ids[ $p ] ?? $d[ 'score_' . $p . '_id' ] ?? $d['external_ids'][ $p ] ?? '' ) );
                if ( ! $id ) { $warnings[] = 'Enter the ' . strtoupper( $p ) . ' game ID' . ( 'rawg' === $p ? ' or slug' : '' ) . ' to fetch its score.'; continue; }
                if ( ! preg_match( 'rawg' === $p ? '/^[a-z0-9-]{1,100}$/D' : '/^[1-9][0-9]{0,8}$/D', $id ) ) { $warnings[] = 'Invalid ' . $p . ' game ID.'; continue; }
                try {
                    $game = self::http( $p, ( 'rawg' === $p ? '/games/' : '/game/' ) . $id ); $matches[] = ucfirst( $p ) . ': ' . sanitize_text_field( $game['name'] );
                    $field = 'rawg' === $p ? 'metacritic' : 'opencritic'; $score = ReviewScores::value( $game[ 'rawg' === $p ? 'metacritic' : 'topCriticScore' ] ?? '' );
                    if ( null === $score || ( 'opencritic' === $p && isset( $game['numReviews'] ) && 0 === (int) $game['numReviews'] ) ) { $warnings[] = ucfirst( $p ) . ' has no critic score for this game yet.'; continue; }
                    $values[ $field . '_score' ] = $score; $values[ 'score_' . $p . '_id' ] = $id;
                    if ( 'rawg' === $p ) { $link = ReviewScores::provider_url( 'metacritic', $game['metacritic_url'] ?? '' ); if ( $link ) { $values['metacritic_url'] = $link; } $slug = $game['slug'] ?? ''; if ( is_string( $slug ) && preg_match( '/^[a-z0-9-]+$/D', $slug ) ) { $values['metacritic_data_source'] = 'https://rawg.io/games/' . $slug; } }
                    else { $values['opencritic_url'] = 'https://opencritic.com/game/' . $id; }
                } catch ( \Throwable $e ) { $warnings[] = $e instanceof SourceError ? $e->getMessage() : 'The game score lookup failed. Please retry.'; }
            }
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
        return array( 'values' => Schema::values( $r['type'], $values ), 'warnings' => $warnings, 'matches' => $matches );
    }
    public static function boot(): void {
        add_action( 'wp_ajax_msc_score_preview', static function () {
            check_ajax_referer( 'msc_scores_' . get_current_blog_id(), 'nonce' );
            try { if ( ! is_string( $_POST['record'] ?? null ) ) { throw new SourceError( 'Invalid score preview request.' ); } $ids = array(); foreach ( array( 'imdb', 'rawg', 'opencritic' ) as $p ) { if ( isset( $_POST[ $p ] ) && ! is_string( $_POST[ $p ] ) ) { throw new SourceError( 'Invalid provider ID.' ); } if ( ! empty( $_POST[ $p ] ) ) { $ids[ $p ] = wp_unslash( $_POST[ $p ] ); } } wp_send_json_success( self::preview( wp_unslash( $_POST['record'] ), $ids ) ); }
            catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => $e instanceof SourceError ? $e->getMessage() : 'Score preview failed. Please retry.' ), $e instanceof SourceError ? $e->status : 502 ); }
        } );
        add_action( 'admin_enqueue_scripts', static function () {
            if ( ! current_user_can( 'msc_edit_global' ) && ! current_user_can( 'msc_override_local' ) ) { return; }
            $id = $_GET['record'] ?? ''; if ( ! is_scalar( $id ) || ! $id ) { return; } $r = Plugin::store()->resolved( (string) $id ); if ( ! $r || ! ReviewScores::providers( $r['type'] ) ) { return; }
            wp_enqueue_script( 'msc-score-editor', plugins_url( 'assets/scores-editor.js', MSC_FILE ), array(), MSC_VERSION, true ); wp_localize_script( 'msc-score-editor', 'mscScoreImport', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'record' => $r['uuid'], 'nonce' => wp_create_nonce( 'msc_scores_' . get_current_blog_id() ) ) );
        } );
        add_action( 'network_admin_menu', static function () { add_submenu_page( 'msc-network', 'Review Score Sources', 'Review Score Sources', 'manage_network_options', 'msc-score-sources', array( self::class, 'settings' ) ); }, 20 );
        add_action( 'admin_post_msc_score_settings', static function () {
            check_admin_referer( 'msc_score_settings' ); if ( ! current_user_can( 'manage_network_options' ) ) { wp_die( 'Network permission required.', '', array( 'response' => 403 ) ); }
            $changes = array(); foreach ( array( 'rawg', 'opencritic' ) as $p ) { if ( self::managed( $p ) ) { continue; } $v = $_POST[ $p ] ?? ''; if ( ! is_string( $v ) || ( trim( $v ) && ! preg_match( '/^[A-Za-z0-9_-]{4,256}$/D', trim( $v ) ) ) ) { wp_die( 'Invalid API key.' ); } $changes[ $p ] = ! empty( $_POST[ 'clear_' . $p ] ) ? null : trim( wp_unslash( $v ) ); }
            foreach ( $changes as $p => $key ) { if ( null === $key ) { delete_network_option( get_current_network_id(), 'msc_score_key_' . $p ); } elseif ( '' !== $key ) { update_network_option( get_current_network_id(), 'msc_score_key_' . $p, $key ); } }
            wp_safe_redirect( network_admin_url( 'admin.php?page=msc-score-sources&saved=1' ) ); exit;
        } );
    }
    public static function settings(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        echo '<div class="wrap"><h1>Review Score Sources</h1><p>Movie/TV scores use the existing OMDb key. Game Metacritic scores use RAWG; OpenCritic uses its official RapidAPI subscription. Choose provider plans suitable for your commercial usage.</p><p><a href="https://rawg.io/apidocs">RAWG API access</a> · <a href="https://rapidapi.com/opencritic-opencritic-default/api/opencritic-api">OpenCritic API access</a> · <a href="https://www.rottentomatoes.com/help_desk/licensing">Rotten Tomatoes data licensing</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_score_settings">'; wp_nonce_field( 'msc_score_settings' );
        foreach ( array( 'rawg' => 'RAWG API key', 'opencritic' => 'OpenCritic RapidAPI key' ) as $p => $label ) { echo '<h2>' . esc_html( $label ) . '</h2><p>' . ( self::key( $p ) ? 'Configured' : 'Not configured' ) . ( self::managed( $p ) ? ' · Managed by server configuration' : '' ) . '</p>'; if ( ! self::managed( $p ) ) { echo '<input type="password" name="' . esc_attr( $p ) . '" value="" class="regular-text" autocomplete="new-password"><p><label><input type="checkbox" name="clear_' . esc_attr( $p ) . '" value="1"> Remove saved key</label></p>'; } }
        echo '<p>Blank fields retain existing keys. Keys never appear in score previews or public pages. Fetch scores in the catalog item’s ACF or full shared editor, review the matched titles, then save the selected values. Cached provider responses are reused for six hours.</p>'; submit_button( 'Save score source settings' ); echo '</form></div>';
    }
}
