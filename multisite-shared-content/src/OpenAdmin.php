<?php
namespace MSC;

final class OpenAdmin {
    public static function boot(): void {
        add_action( 'admin_menu', static function () { if ( OpenImport::allowed() ) { add_submenu_page( 'msc', 'Import Content', 'Import Content', 'read', 'msc-import', array( self::class, 'page' ) ); } }, 20 );
        add_action( 'admin_post_msc_open_import', array( self::class, 'handle' ) );
        add_action( 'admin_post_msc_open_batch', array( self::class, 'start_batch' ) );
        add_action( 'wp_ajax_msc_open_step', array( self::class, 'step' ) );
        add_action( 'admin_enqueue_scripts', static function () {
            if ( 'msc-import' !== ( $_GET['page'] ?? '' ) ) { return; }
            wp_enqueue_script( 'msc-open-import', plugins_url( 'assets/import.js', MSC_FILE ), array(), MSC_VERSION, true );
        } );
    }
    public static function link( string $record = '' ): string { return admin_url( 'admin.php?page=msc-import' . ( $record ? '&record=' . rawurlencode( $record ) : '' ) ); }
    private static function hidden( string $key, string $value ): void { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
    private static function url( array $params, string $record = '' ): string { return add_query_arg( $params + array( '_wpnonce' => wp_create_nonce( 'msc_open_search' ) ), self::link( $record ) ); }
    private static function nonce(): void { if ( ! is_string( $_GET['_wpnonce'] ?? null ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'msc_open_search' ) ) { throw new SourceError( 'The search token expired. Submit the search form again.', 403 ); } }
    public static function page(): void {
        if ( ! OpenImport::allowed() ) { wp_die( 'Global editing permission and an enrolled content type are required.', 'Import Content', array( 'response' => 403 ) ); }
        echo '<div class="wrap msc msc-omdb"><h1>Import Content</h1><p>Search, review and import shared fields. New records follow your publication defaults. Existing site overrides are retained.</p><p>Keyless sources: <a href="https://www.tvmaze.com/api">TVmaze</a> (CC BY-SA), <a href="https://www.wikidata.org/wiki/Wikidata:Licensing">Wikidata</a> (CC0), Wikipedia text (CC BY-SA), and individually licensed Commons images. Attribution travels with imported fields. ShareAlike applies to reused/adapted licensed content.</p>';
        try {
            if ( isset( $_GET['done'] ) ) { self::receipt( sanitize_text_field( wp_unslash( $_GET['done'] ) ) ); }
            if ( isset( $_GET['job'] ) ) { self::job( sanitize_text_field( wp_unslash( $_GET['job'] ) ) ); echo '</div>'; return; }
            $record = sanitize_text_field( wp_unslash( $_GET['record'] ?? '' ) ); $r = $record ? Plugin::store()->get( $record ) : null;
            if ( $record && ! $r ) { throw new SourceError( 'The destination record was not found.', 404 ); }
            $type = $r['type'] ?? sanitize_key( $_GET['kind'] ?? 'msc_movie' );
            if ( ! isset( OpenImport::TYPES[ $type ] ) || ! Schema::enabled( $type ) ) { throw new SourceError( 'Enable this content type on the site before importing.' ); }
            if ( $r ) { echo '<p class="notice notice-info inline">Destination: <strong>' . esc_html( $r['data']['title'] ?? $r['uuid'] ) . '</strong>. Review the source identity before connecting it.</p>'; }
            $query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ); $provider = sanitize_key( $_GET['provider'] ?? 'wikidata' );
            echo '<form method="get" class="msc-omdb-search" action="' . esc_url( admin_url( 'admin.php' ) ) . '">'; self::hidden( 'page', 'msc-import' ); self::hidden( 'record', $record ); wp_nonce_field( 'msc_open_search', '_wpnonce', false );
            echo '<label>Content type<select name="kind">'; foreach ( OpenImport::TYPES as $key => $label ) { if ( ! Schema::enabled( $key ) || $r && $r['type'] !== $key ) { continue; } echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $type, false ) . '>' . esc_html( $label ) . '</option>'; } echo '</select></label>';
            echo '<label>Title or person name<input type="search" name="q" maxlength="160" required value="' . esc_attr( $query ) . '"></label><label>People source<select name="provider"><option value="wikidata"' . selected( $provider, 'wikidata', false ) . '>Wikidata + Wikipedia</option><option value="tvmaze"' . selected( $provider, 'tvmaze', false ) . '>TVmaze (TV people)</option></select></label><button class="button button-primary">Search</button></form>';
            echo '<p class="description">Movies use Wikidata. Shows and Episodes use TVmaze. For Episodes, search for the show first, then choose a season. People source only affects People searches.</p>';
            if ( isset( $_GET['id'] ) ) { self::nonce(); self::preview( OpenImport::preview( $provider, sanitize_text_field( $_GET['id'] ), $type, $record, sanitize_text_field( $_GET['parent'] ?? '' ) ) ); }
            elseif ( isset( $_GET['show'] ) ) { self::nonce(); self::episodes( sanitize_text_field( $_GET['show'] ), $record ); }
            elseif ( '' !== $query ) {
                self::nonce(); $result = OpenSources::search( $type, $query, $provider, absint( $_GET['offset'] ?? 0 ) );
                echo '<h2>Search results</h2><div class="msc-omdb-results">';
                foreach ( $result['items'] as $item ) {
                    $params = 'msc_episode' === $type ? array( 'kind' => $type, 'show' => $item['id'] ) : array( 'kind' => $type, 'provider' => $item['provider'], 'id' => $item['id'] );
                    echo '<article class="msc-omdb-result"><div><h3>' . esc_html( $item['title'] ) . '</h3><p>' . esc_html( $item['description'] ) . '</p><p><code>' . esc_html( $item['provider'] . ':' . $item['id'] ) . '</code></p><a class="button" href="' . esc_url( self::url( $params, $record ) ) . '">' . ( 'msc_episode' === $type ? 'Choose episodes from ' : 'Preview ' ) . esc_html( $item['title'] ) . '</a></div></article>';
                }
                if ( ! $result['items'] ) { echo '<p>No matching items of this type on this page. Try a fuller name, another title, or the next results page.</p>'; } echo '</div>';
                if ( null !== $result['next'] ) { echo '<a class="button" href="' . esc_url( self::url( array( 'kind' => $type, 'provider' => $provider, 'q' => $query, 'offset' => $result['next'] ), $record ) ) . '">More results</a>'; }
            }
        } catch ( \Throwable $e ) { echo '<div class="notice notice-error inline"><p>' . esc_html( $e->getMessage() ) . '</p></div>'; }
        echo '</div>';
    }
    private static function preview( array $s ): void {
        $r = $s['target'] ? Plugin::store()->get( $s['target'] ) : null; $schema = Schema::get( $s['type'] );
        echo '<section class="msc-omdb-preview"><div class="msc-omdb-preview-heading">';
        if ( $s['image'] ) { echo '<img src="' . esc_url( $s['image']['url'] ) . '" alt="' . esc_attr( $s['fields']['title'] ?? '' ) . '" referrerpolicy="no-referrer">'; }
        echo '<div><h2>Review ' . esc_html( $s['fields']['title'] ?? $s['id'] ) . '</h2><p>' . ( $r ? 'Update selected global fields on the existing record.' : 'Create a shared record.' ) . '</p>' . Attribution::source_html( $s['source'] ) . '</div></div>';
        if ( $r && ! empty( $r['data']['additional_metadata']['omdb'] ) ) { echo '<div class="notice notice-warning inline"><p>This record contains legacy OMDb data, which was supplied under a noncommercial licence. Replacing selected fields does not clear the remaining legacy text or images. Review them before commercial publication.</p></div>'; }
        foreach ( $s['warnings'] as $warning ) { echo '<p class="description">' . esc_html( $warning ) . '</p>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; self::hidden( 'action', 'msc_open_import' ); self::hidden( 'token', $s['token'] ); wp_nonce_field( 'msc_open_import', 'nonce', false );
        echo '<table class="widefat striped msc-omdb-fields"><thead><tr><th>Import</th><th>Field</th><th>Source value and licence</th>' . ( $r ? '<th>Current global value</th>' : '' ) . '</tr></thead><tbody>';
        foreach ( $s['fields'] as $key => $value ) {
            $display = 'parent_show' === $key ? ( Plugin::store()->get( $value )['data']['title'] ?? $value ) : ( is_array( $value ) ? implode( ', ', $value ) : wp_strip_all_tags( (string) $value ) );
            echo '<tr><td><input aria-label="Import ' . esc_attr( $key ) . '" type="checkbox" name="fields[]" value="' . esc_attr( $key ) . '"' . checked( ! $r, true, false ) . '></td><th>' . esc_html( $schema['fields'][ $key ]['label'] ?? ucwords( str_replace( '_', ' ', $key ) ) ) . '</th><td>' . esc_html( $display ) . '<p class="description">' . Attribution::source_html( $s['field_sources'][ $key ] ?? $s['source'] ) . '</p></td>';
            if ( $r ) { $old = $r['data'][ $key ] ?? ''; if ( 'parent_show' === $key && $old ) { $old = Plugin::store()->get( $old )['data']['title'] ?? $old; } echo '<td>' . esc_html( is_array( $old ) ? implode( ', ', array_filter( $old, 'is_scalar' ) ) : wp_strip_all_tags( (string) $old ) ) . '</td>'; } echo '</tr>';
        }
        echo '</tbody></table><p class="description">External source IDs and the attribution for selected fields are retained automatically.</p>';
        if ( $s['roles'] ) { echo '<h3>Linked People</h3><p>Selected credit fields are replaced with these source-identified People. Existing source IDs are reused; new People follow your publication defaults. Name-only records are retained separately until you explicitly connect their identity from their own import screen.</p>'; foreach ( $s['roles'] as $role => $names ) { echo '<p><label><input type="checkbox" name="roles[]" value="' . esc_attr( $role ) . '"' . checked( ! $r && current_user_can( 'msc_create_global' ), true, false ) . '> ' . esc_html( ucwords( $role ) . ': ' . implode( ', ', $names ) ) . '</label></p>'; } }
        if ( $s['image'] ) { echo '<h3>Licensed image</h3><p>' . Attribution::source_html( $s['image']['source'] ) . '</p><p><label><input type="checkbox" name="image" value="1"> Import this image and its credit (replaces the current image)</label></p>'; }
        submit_button( $r ? 'Import selected global fields' : 'Create shared record' ); echo '</form></section>';
    }
    private static function episodes( string $id, string $target ): void {
        $show = OpenSources::show( OpenClient::numeric( $id ) ); $parents = OpenImport::matches( 'msc_show', array( 'tvmaze' => $id ) ); $parent = 1 === count( $parents ) && 'active' === $parents[0]['state'] ? $parents[0] : null;
        echo '<h2>Episodes of ' . esc_html( $show['name'] ) . '</h2>' . Attribution::source_html( OpenSources::source( 'tvmaze', 'https://www.tvmaze.com/shows/' . $id ) );
        if ( ! $parent ) { echo '<p>Import or connect this TV Show before importing its Episodes. If this Show already exists, open its global editor and use Import Content to connect its TVmaze identity.</p><p><a class="button button-primary" href="' . esc_url( self::url( array( 'provider' => 'tvmaze', 'kind' => 'msc_show', 'id' => $id ) ) ) . '">Import TV Show first</a></p>'; return; }
        if ( ! Schema::enabled( 'msc_episode' ) || ! Schema::enabled( 'msc_show' ) ) { throw new SourceError( 'Enable TV Shows and Episodes on this site first.' ); }
        $episodes = OpenSources::episodes( $id ); $seasons = array(); foreach ( $episodes as $ep ) { $key = null === $ep['season'] ? 'unknown' : (string) $ep['season']; $seasons[ $key ] = 'unknown' === $key ? 'Unnumbered season' : 'Season ' . $key; }
        $season = sanitize_text_field( $_GET['season'] ?? (string) ( array_key_first( $seasons ) ?? '' ) );
        echo '<p>Parent Show: <strong>' . esc_html( $parent['data']['title'] ) . '</strong>. Episode IDs are checked against this Show on every import.</p><nav class="msc-season-nav" aria-label="Seasons">'; foreach ( $seasons as $key => $label ) { echo '<a class="button' . ( (string) $key === $season ? ' button-primary' : '' ) . '" href="' . esc_url( self::url( array( 'kind' => 'msc_episode', 'show' => $id, 'season' => $key ), $target ) ) . '">' . esc_html( $label ) . '</a> '; } echo '</nav>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; self::hidden( 'action', 'msc_open_batch' ); self::hidden( 'parent', $parent['uuid'] ); wp_nonce_field( 'msc_open_batch' );
        if ( ! $target ) { echo '<p><label><input type="checkbox" data-msc-select-episodes> Select all episodes in this season</label></p>'; }
        echo '<table class="widefat striped"><thead><tr><th>Select</th><th>Episode</th><th>Title</th><th>Air date</th><th>Action</th></tr></thead><tbody>';
        $count = 0; foreach ( $episodes as $ep ) {
            if ( ( null === $ep['season'] ? 'unknown' : (string) $ep['season'] ) !== $season ) { continue; } ++$count;
            echo '<tr><td>' . ( $target ? '' : '<input aria-label="Select ' . esc_attr( $ep['title'] ) . '" type="checkbox" name="episodes[]" value="' . esc_attr( $ep['id'] ) . '">' ) . '</td><td>' . esc_html( null === $ep['number'] ? 'Special / unnumbered' : (string) $ep['number'] ) . '</td><td>' . esc_html( $ep['title'] ) . '</td><td>' . esc_html( $ep['date'] ?: 'Not announced' ) . '</td><td><a class="button" href="' . esc_url( self::url( array( 'provider' => 'tvmaze', 'kind' => 'msc_episode', 'id' => $ep['id'], 'parent' => $parent['uuid'] ), $target ) ) . '">Preview episode</a></td></tr>';
        }
        echo '</tbody></table>'; if ( ! $count ) { echo '<p>No episodes are listed for this season.</p>'; }
        if ( ! $target ) { echo '<p><label><input type="checkbox" name="refresh" value="1"> Refresh existing Episodes as well: replace available source fields while retaining local overrides and fields the source does not supply.</label></p><p>Imports run one episode at a time with progress and a separate result for each. You can pause and resume this batch for 24 hours. New Episodes follow the publication default; this does not publish the season.</p>'; submit_button( 'Import selected episodes' ); } echo '</form>';
    }
    public static function handle(): void {
        try {
            $result = OpenImport::import( wp_unslash( $_POST ) ); $token = wp_generate_uuid4(); set_site_transient( 'msc_open_receipt_' . $token, array( 'user' => get_current_user_id(), 'site' => get_current_blog_id(), 'result' => $result ), 30 * MINUTE_IN_SECONDS ); wp_safe_redirect( add_query_arg( 'done', $token, self::link() ) ); exit;
        } catch ( \Throwable $e ) { self::failure( $e ); }
    }
    private static function failure( \Throwable $e ): void { wp_die( esc_html( $e->getMessage() ), 'Import needs review', array( 'response' => $e instanceof SourceError ? $e->status : ( str_starts_with( $e->getMessage(), 'Conflict:' ) ? 409 : 400 ), 'back_link' => true ) ); }
    private static function receipt( string $token ): void {
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) { return; } $receipt = get_site_transient( 'msc_open_receipt_' . $token );
        if ( ! $receipt || $receipt['user'] !== get_current_user_id() || $receipt['site'] !== get_current_blog_id() ) { return; }
        $r = $receipt['result']['record']; echo '<div class="notice notice-success inline"><p><strong>Saved: ' . esc_html( $r['data']['title'] ?? $r['uuid'] ) . '</strong></p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] ) ) . '">All fields and publishing</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] . '&scope=global' ) ) . '">Edit with ACF</a></p>';
        if ( 'msc_show' === $r['type'] && ! empty( $r['data']['external_ids']['tvmaze'] ) ) { echo '<p><a class="button button-primary" href="' . esc_url( self::url( array( 'kind' => 'msc_episode', 'show' => $r['data']['external_ids']['tvmaze'] ) ) ) . '">Choose seasons and episodes</a></p>'; }
        foreach ( $receipt['result']['warnings'] as $warning ) { echo '<p>' . esc_html( $warning ) . '</p>'; } echo '</div>';
    }
    public static function start_batch(): void {
        check_admin_referer( 'msc_open_batch' );
        try { $token = OpenImport::batch_start( sanitize_text_field( $_POST['parent'] ?? '' ), wp_unslash( $_POST['episodes'] ?? array() ), ! empty( $_POST['refresh'] ) ); wp_safe_redirect( add_query_arg( 'job', $token, self::link() ) ); exit; } catch ( \Throwable $e ) { self::failure( $e ); }
    }
    private static function job( string $token ): void {
        $job = OpenImport::batch( $token );
        echo '<section id="msc-import-job" data-token="' . esc_attr( $token ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'msc_open_step' ) ) . '" data-total="' . count( $job['items'] ) . '"><h2>Episode import progress</h2><p class="msc-job-status" role="status">' . (int) $job['index'] . ' / ' . count( $job['items'] ) . ' processed</p><progress max="' . count( $job['items'] ) . '" value="' . (int) $job['index'] . '"></progress><p><button class="button button-primary" type="button" data-msc-resume>Start / resume</button> <button class="button" type="button" data-msc-pause>Pause after current episode</button></p><p>Keep this page open while importing. Reloading this URL lets the same user resume within 24 hours. Episodes marked “Needs review” can be previewed and retried individually.</p><ol class="msc-job-results">';
        foreach ( $job['results'] as $row ) { echo '<li>' . esc_html( $row['title'] . ' — ' . $row['status'] ) . '</li>'; } echo '</ol></section>';
    }
    public static function step(): void {
        check_ajax_referer( 'msc_open_step', 'nonce' );
        try { $job = OpenImport::batch_step( sanitize_text_field( $_POST['token'] ?? '' ) ); wp_send_json_success( array( 'index' => $job['index'], 'total' => count( $job['items'] ), 'results' => $job['results'] ) ); }
        catch ( \Throwable $e ) { wp_send_json_error( array( 'message' => $e->getMessage(), 'retry' => $e instanceof SourceError && 429 === $e->status ), $e instanceof SourceError ? $e->status : 400 ); }
    }
}
