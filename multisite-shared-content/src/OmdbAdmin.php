<?php
namespace MSC;

/** Server-rendered title search and review forms; no API key is sent to JavaScript. */
final class OmdbAdmin {
    public static function boot(): void {
        add_action( 'admin_menu', static function () { if ( OmdbImport::allowed() ) { add_submenu_page( 'msc', 'Search OMDb', 'Search OMDb', 'read', 'msc-omdb', array( self::class, 'page' ) ); } }, 20 );
        add_action( 'network_admin_menu', static function () { add_submenu_page( 'msc-network', 'OMDb API', 'OMDb API', 'manage_network_options', 'msc-omdb-settings', array( self::class, 'settings' ) ); }, 20 );
        add_action( 'admin_post_msc_omdb_key', array( self::class, 'save_key' ) );
        add_action( 'admin_post_msc_omdb_import', array( self::class, 'handle' ) );
    }
    public static function link( string $record = '' ): string { return admin_url( 'admin.php?page=msc-omdb' . ( $record ? '&record=' . rawurlencode( $record ) : '' ) ); }
    public static function settings(): void {
        Permissions::require( 'manage_network_options' );
        echo '<div class="wrap msc"><h1>OMDb API</h1><p>Search titles and import selected information into shared Movies, TV Shows and Episodes.</p>';
        if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success inline"><p>OMDb settings saved.</p></div>'; }
        echo '<p><strong>Status: ' . ( OmdbClient::key() ? 'Configured' : 'Not configured' ) . '</strong>' . ( OmdbClient::configured_by_server() ? ' · Managed by server configuration.' : '' ) . '</p>';
        if ( ! OmdbClient::configured_by_server() ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'msc_omdb_key' );
            echo '<input type="hidden" name="action" value="msc_omdb_key"><table class="form-table"><tr><th><label for="msc-omdb-key">OMDb API key</label></th><td><input type="password" class="regular-text" id="msc-omdb-key" name="omdb_key" value="" autocomplete="new-password"><p class="description">Leave blank to retain the saved key. The saved value is never displayed.</p><p><label><input type="checkbox" name="clear" value="1"> Remove the saved key</label></p></td></tr></table>'; submit_button( 'Save OMDb settings' ); echo '</form>';
        }
        echo '<p>Use <strong>Shared Content → Search OMDb</strong> on an enrolled site. Search is available to global creators/editors. The key stays on the server and is shared by this network.</p><p>The free OMDb plan allows 1,000 requests per day. Search results are cached for 15 minutes and title details for six hours. <a href="https://www.omdbapi.com/" target="_blank" rel="noopener">OMDb documentation</a></p></div>';
    }
    public static function save_key(): void {
        check_admin_referer( 'msc_omdb_key' );
        try { OmdbClient::set_key( wp_unslash( (string) ( $_POST['omdb_key'] ?? '' ) ), ! empty( $_POST['clear'] ) ); wp_safe_redirect( network_admin_url( 'admin.php?page=msc-omdb-settings&saved=1' ) ); exit; }
        catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'OMDb settings were not saved', array( 'response' => 403, 'back_link' => true ) ); }
    }
    private static function hidden( string $name, $value ): void { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">'; }
    private static function image( string $url, string $title ): void { if ( $url ) { echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $title ) . ' poster" referrerpolicy="no-referrer" loading="lazy">'; } else { echo '<div class="msc-omdb-no-poster">No poster available</div>'; } }
    public static function page(): void {
        if ( ! OmdbImport::allowed() ) { wp_die( 'Global editing permission and an enrolled Movie, TV Show or Episode type are required.', 'OMDb', array( 'response' => 403 ) ); }
        echo '<div class="wrap msc msc-omdb"><h1>Search OMDb</h1><p>Find a title, review its information, then choose which shared fields to import. Existing site overrides remain intact.</p>';
        try {
            if ( ! empty( $_GET['done'] ) ) { self::receipt( sanitize_text_field( $_GET['done'] ) ); }
            $record = sanitize_text_field( $_GET['record'] ?? '' ); $destination = $record ? Plugin::store()->get( $record ) : null;
            if ( $record && ( ! $destination || ! in_array( $destination['type'], OmdbImport::TYPES, true ) || ! Schema::enabled( $destination['type'] ) ) ) { throw new OmdbError( 'Choose an enrolled Movie, TV Show or Episode record.' ); }
            if ( $destination ) { echo '<div class="notice notice-info inline"><p>Destination: <strong>' . esc_html( $destination['data']['title'] ?? '(Untitled)' ) . '</strong>. An import updates selected global fields.</p></div>'; }
            $query = sanitize_text_field( $_GET['q'] ?? '' ); $year = sanitize_text_field( $_GET['year'] ?? '' );
            $kind = sanitize_key( $_GET['kind'] ?? ( $destination ? array_search( $destination['type'], OmdbImport::TYPES, true ) : '' ) );
            echo '<form method="get" class="msc-omdb-search" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
            self::hidden( 'page', 'msc-omdb' ); self::hidden( 'record', $record ); wp_nonce_field( 'msc_omdb_search', '_wpnonce', false );
            echo '<label>Title <input type="search" name="q" value="' . esc_attr( $query ) . '" maxlength="200" required></label><label>Type <select name="kind">';
            foreach ( array( '' => 'All titles', 'movie' => 'Movies', 'series' => 'TV Shows', 'episode' => 'Episodes' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $kind, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select></label><label>Year <input type="text" name="year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="Optional" value="' . esc_attr( $year ) . '"></label>'; submit_button( 'Search titles', 'primary', 'search', false ); echo '</form>';
            if ( ! OmdbClient::key() ) { echo '<div class="notice notice-warning inline"><p>An administrator must configure the OMDb API key under <a href="' . esc_url( network_admin_url( 'admin.php?page=msc-omdb-settings' ) ) . '">Network Admin → Shared Content → OMDb API</a>.</p></div>'; }
            $imdb = sanitize_text_field( $_GET['imdb'] ?? '' );
            if ( '' !== $query || '' !== $imdb ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'msc_omdb_search' ) ) { throw new OmdbError( 'The search token expired. Submit the search form again.', 403 ); }
                if ( $imdb ) { self::preview( OmdbImport::preview( $imdb, $record ) ); }
                else {
                    $page = isset( $_GET['omdb_page'] ) ? (int) $_GET['omdb_page'] : 1; $result = OmdbClient::search( $query, $kind, $year, $page );
                    echo '<h2>Search results</h2><p>' . esc_html( (string) $result['totalResults'] ) . ' matching titles. Select Preview to see full details.</p><div class="msc-omdb-results">';
                    foreach ( $result['Search'] as $item ) {
                        $url = add_query_arg( array( 'imdb' => $item['imdbID'], '_wpnonce' => wp_create_nonce( 'msc_omdb_search' ) ), self::link( $record ) );
                        echo '<article class="msc-omdb-result">'; self::image( $item['Poster'], $item['Title'] ); echo '<div><h3>' . esc_html( $item['Title'] ) . '</h3><p>' . esc_html( $item['Year'] . ' · ' . array( 'movie' => 'Movie', 'series' => 'TV Show', 'episode' => 'Episode' )[ $item['Type'] ] ) . '</p><p><code>' . esc_html( $item['imdbID'] ) . '</code></p><a class="button" href="' . esc_url( $url ) . '">Preview ' . esc_html( $item['Title'] ) . '</a></div></article>';
                    }
                    if ( ! $result['Search'] ) { echo '<p>No matching titles. Try another title or year.</p>'; } echo '</div><nav class="msc-omdb-pagination" aria-label="Search pages">';
                    $pages = min( 100, (int) ceil( $result['totalResults'] / 10 ) );
                    foreach ( array( $page - 1 => 'Previous page', $page + 1 => 'Next page' ) as $number => $label ) { if ( $number >= 1 && $number <= $pages ) { $url = add_query_arg( array( 'q' => $query, 'kind' => $kind, 'year' => $year, 'omdb_page' => $number, '_wpnonce' => wp_create_nonce( 'msc_omdb_search' ) ), self::link( $record ) ); echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> '; } } echo '</nav>';
                }
            }
        } catch ( \Throwable $e ) { echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html( $e->getMessage() ) . '</p></div>'; }
        echo '</div>';
    }
    private static function preview( array $s ): void {
        $r = $s['target'] ? Plugin::store()->get( $s['target'] ) : null; $schema = Schema::get( $s['type'] );
        echo '<section class="msc-omdb-preview"><div class="msc-omdb-preview-heading">'; self::image( $s['data']['Poster'], $s['data']['Title'] );
        echo '<div><h2>' . esc_html( $s['data']['Title'] ) . '</h2><p>' . esc_html( ( $s['data']['Year'] ?? '' ) . ' · ' . $schema['label'] . ' · ' . $s['imdb'] ) . '</p><p>' . esc_html( $s['data']['Plot'] ?? '' ) . '</p><p><strong>' . ( $r ? 'Update the existing shared record' : 'Create a shared record' ) . '</strong></p>';
        if ( $r ) { echo '<p>Fields start unchecked. Select the values you want to replace globally.</p>'; }
        else { echo '<p>New records use the configured publication default (draft in the supplied setup). Other participating sites receive the shared record.</p>'; }
        echo '</div></div>';
        foreach ( $s['warnings'] as $warning ) { echo '<p class="description">' . esc_html( $warning ) . '</p>'; }
        echo '<form data-msc-import-selection method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; self::hidden( 'action', 'msc_omdb_import' ); self::hidden( 'token', $s['token'] ); wp_nonce_field( 'msc_omdb_import', 'nonce', false );
        echo '<table class="widefat striped msc-omdb-fields"><thead><tr><th><label><input type="checkbox" data-msc-select-all aria-label="Select all import data"> All</label></th><th>Field</th><th>OMDb value</th>' . ( $r ? '<th>Current shared value</th>' : '' ) . '</tr></thead><tbody>';
        foreach ( $s['fields'] as $key => $value ) {
            $label = $schema['fields'][ $key ]['label'] ?? ucwords( str_replace( '_', ' ', $key ) ); $native_title = 'post_title' === ( $schema['fields'][ $key ]['property'] ?? '' );
            echo '<tr><td><input type="checkbox" data-msc-import-option name="fields[]" value="' . esc_attr( $key ) . '" aria-label="Import ' . esc_attr( $label ) . '"' . checked( ! $r, true, false ) . ( ! $r && $native_title ? ' required' : '' ) . '></td><th scope="row">' . esc_html( $label ) . '</th><td>';
            if ( 'parent_show' === $key ) {
                echo '<select name="parent_show" aria-label="Parent TV Show"><option value="">— Choose the TV Show —</option>'; foreach ( OmdbImport::shows() as $uuid => $name ) { echo '<option value="' . esc_attr( $uuid ) . '"' . selected( $value ?: ( $r['data']['parent_show'] ?? '' ), $uuid, false ) . '>' . esc_html( $name ) . '</option>'; } echo '</select><p class="description">Import the TV Show first if it is missing.</p>';
            } elseif ( 'additional_metadata' === $key ) { echo '<span>IMDb ratings/votes, awards, box office, original country/language lists, year, and source details.</span>'; foreach ( $s['data']['Ratings'] as $rating ) { echo '<p>' . esc_html( $rating['Source'] . ': ' . $rating['Value'] ) . '</p>'; } }
            else { echo esc_html( is_array( $value ) ? implode( ', ', $value ) : wp_strip_all_tags( (string) $value ) ); }
            echo '</td>'; if ( $r ) { $current = $r['data'][ $key ] ?? ''; echo '<td>' . esc_html( is_array( $current ) ? ( 'additional_metadata' === $key ? 'Existing metadata is retained; selected OMDb keys are refreshed.' : implode( ', ', array_map( static fn( $item ) => is_scalar( $item ) ? (string) $item : '…', $current ) ) ) : wp_strip_all_tags( (string) $current ) ) . '</td>'; } echo '</tr>';
        }
        echo '</tbody></table><p class="description">IMDb ID is always retained in External IDs. Fields not selected are preserved. No local override is reset by an import.</p>';
        if ( $s['poster_field'] && $s['data']['Poster'] ) { echo '<p><label><input type="checkbox" data-msc-import-option name="poster" value="1"' . checked( ! $r && current_user_can( 'upload_files' ), true, false ) . disabled( ! current_user_can( 'upload_files' ), true, false ) . '> Import the poster into this site’s media library</label></p>'; }
        if ( $s['credits'] ) {
            echo '<h3>Review Person links</h3><p>OMDb supplies names without Person IDs. Review possible existing People, including drafts, before creating another record. One Person is reused across selected credit roles.</p>'; PersonReview::choices( $s['credits'], $s['person_matches'] );
        }
        submit_button( $r ? 'Import selected global fields' : 'Create shared record from OMDb' ); echo '</form></section>';
    }
    public static function handle(): void {
        try { $result = OmdbImport::import( wp_unslash( $_POST ) ); $token = wp_generate_uuid4(); set_site_transient( 'msc_omdb_receipt_' . $token, array( 'user' => get_current_user_id(), 'site' => get_current_blog_id(), 'result' => $result ), 15 * MINUTE_IN_SECONDS ); wp_safe_redirect( add_query_arg( 'done', $token, self::link() ) ); exit; }
        catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'OMDb import was not saved', array( 'response' => $e instanceof OmdbError ? $e->status : ( str_starts_with( $e->getMessage(), 'Conflict:' ) ? 409 : ( $e instanceof \InvalidArgumentException ? 400 : 403 ) ), 'back_link' => true ) ); }
    }
    private static function receipt( string $token ): void {
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) { return; } $receipt = get_site_transient( 'msc_omdb_receipt_' . $token );
        if ( ! $receipt || $receipt['user'] !== get_current_user_id() || $receipt['site'] !== get_current_blog_id() ) { return; }
        $result = $receipt['result']; $r = $result['record'];
        echo '<div class="notice notice-success inline"><p><strong>OMDb import complete: ' . esc_html( $r['data']['title'] ?? '(Untitled)' ) . '</strong></p><p>Shared information is saved. Other participating sites update through the synchronization queue. Local overrides are retained.</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] ) ) . '">All fields and publishing</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] . '&scope=global' ) ) . '">Edit with ACF</a></p></div>';
        foreach ( $result['warnings'] as $warning ) { echo '<div class="notice notice-warning inline"><p>' . esc_html( $warning ) . '</p></div>'; }
        if ( $result['people'] ) { echo '<h2>New Person records</h2><ul>'; foreach ( $result['people'] as $uuid ) { $person = Plugin::store()->get( $uuid ); if ( $person ) { echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $uuid ) ) . '">' . esc_html( $person['data']['title'] ) . '</a> — review and publish on the sites that should show this Person.</li>'; } } echo '</ul>'; }
    }
}
