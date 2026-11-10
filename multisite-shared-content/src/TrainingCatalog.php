<?php
namespace MSC;

/** Bundled CC0 title facts; imports never depend on a live source API. */
final class TrainingCatalog {
    public static function allowed(): bool { return current_user_can( 'manage_network_options' ) && current_user_can( 'msc_create_global' ) && current_user_can( 'msc_edit_global' ); }
    public static function dataset(): array {
        static $data;
        if ( null === $data ) { $data = json_decode( file_get_contents( dirname( MSC_FILE ) . '/data/training-catalog.json' ), true, 64, JSON_THROW_ON_ERROR );
            $counts = array_count_values( array_column( $data['items'] ?? array(), 'type' ) );
            if ( count( $data['items'] ?? array() ) !== 500 || ( $counts['msc_movie'] ?? 0 ) !== 250 || ( $counts['msc_show'] ?? 0 ) !== 250 || count( array_unique( array_column( $data['items'], 'id' ) ) ) !== 500 ) { throw new \RuntimeException( 'The training catalog must contain 250 distinct Movies and 250 distinct TV Shows.' ); }
        }
        return $data;
    }
    private static function sites( string $type ): array {
        $sites = array_values( array_filter( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'spam' => 0, 'archived' => 0 ) ), static fn( $site ) => Schema::enabled( $type, (int) $site->blog_id ) ) );
        usort( $sites, static fn( $a, $b ) => array( 'a' !== NetworkSites::key( $a->path ), (int) $a->blog_id ) <=> array( 'a' !== NetworkSites::key( $b->path ), (int) $b->blog_id ) );
        if ( ! $sites ) { throw new \RuntimeException( 'Enable ' . Schema::get( $type )['label'] . ' on a site before importing.' ); } return $sites;
    }
    private static function existing( array $row ): ?array {
        global $wpdb; $table = Database::table( 'records' ); $clauses = array( "JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.wikidata'))=%s" ); $args = array( $row['type'], $row['id'] );
        if ( ! empty( $row['fields']['external_ids']['imdb'] ) ) { $clauses[] = "JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.imdb'))=%s"; $args[] = $row['fields']['external_ids']['imdb']; }
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE type=%s AND (" . implode( ' OR ', $clauses ) . ') LIMIT 3', ...$args ) );
        if ( count( $ids ) > 1 ) { throw new \RuntimeException( 'Conflicting source identities need review.' ); }
        if ( $ids ) { $record = Plugin::store()->get( (int) $ids[0] ); foreach ( $row['fields']['external_ids'] as $key => $value ) { if ( ! empty( $record['data']['external_ids'][ $key ] ) && (string) $record['data']['external_ids'][ $key ] !== (string) $value ) { throw new \RuntimeException( 'Existing source identifiers conflict; review this record first.' ); } } return $record; }
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE type=%s AND state='active' AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(data,'$.title'))))=%s LIMIT 5", $row['type'], mb_strtolower( trim( $row['fields']['title'] ) ) ) );
        $matches = array(); $date = 'msc_movie' === $row['type'] ? 'release_date' : 'premiere_date';
        foreach ( $ids as $id ) {
            $r = Plugin::store()->get( (int) $id ); $external = $r['data']['external_ids'] ?? array();
            if ( ! empty( $external['wikidata'] ) && $external['wikidata'] !== $row['id'] || ! empty( $external['imdb'] ) && ! empty( $row['fields']['external_ids']['imdb'] ) && $external['imdb'] !== $row['fields']['external_ids']['imdb'] ) { continue; }
            $year = substr( $r['data'][ $date ] ?? '', 0, 4 );
            if ( $year && ! empty( $row['year'] ) && $year === (string) $row['year'] ) { $matches[] = $r; }
            elseif ( ! $year ) { throw new \RuntimeException( 'A matching title without a confirmed year needs review before creating a possible duplicate.' ); }
        }
        if ( count( $matches ) > 1 ) { throw new \RuntimeException( 'Multiple matching titles need review.' ); }
        return $matches[0] ?? null;
    }
    public static function import_row( array $row ): array {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Network training-import permission is required.' ); }
        $sites = self::sites( $row['type'] ); switch_to_blog( (int) $sites[0]->blog_id );
        try { return Database::lock( 'training:' . $row['type'] . ':' . $row['id'], static function () use ( $row, $sites ) {
            $store = Plugin::store(); $existing = self::existing( $row );
            if ( $existing ) {
                if ( 'active' !== $existing['state'] ) { throw new \RuntimeException( 'An existing archived or trashed record was retained.' ); }
                $previous = $existing['data']['external_ids'] ?? array(); $merged = $previous + $row['fields']['external_ids'];
                if ( $merged !== $previous ) { $existing = $store->update( (int) $existing['id'], array( 'external_ids' => $merged ), (int) $existing['version'] ); }
                $r = $existing; $status = 'Reused existing record';
            } else {
                $schema = Schema::get( $row['type'] ); if ( 'json' !== ( $schema['fields']['external_ids']['type'] ?? '' ) || 'json' !== ( $schema['fields']['additional_metadata']['type'] ?? '' ) ) { throw new \RuntimeException( 'External IDs and additional metadata JSON fields are required.' ); }
                $values = array(); $canonical = Schema::defaults()[ $row['type'] ]['fields'];
                foreach ( $row['fields'] as $key => $value ) {
                    $dest = $key;
                    if ( isset( $canonical[ $key ]['property'] ) ) { foreach ( $schema['fields'] as $name => $field ) { if ( ( $field['property'] ?? '' ) === $canonical[ $key ]['property'] ) { $dest = $name; break; } } }
                    if ( ! isset( $schema['fields'][ $dest ] ) ) { continue; }
                    try { $values[ $dest ] = Schema::sanitize( $schema['fields'][ $dest ], $value ); } catch ( \InvalidArgumentException $e ) { continue; }
                }
                $source = array( 'provider' => 'wikidata', 'url' => 'https://www.wikidata.org/wiki/' . $row['id'], 'license' => 'CC0', 'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'attribution' => 'Wikidata contributors', 'changes' => 'Facts selected and formatted for the training catalog.' );
                $provenance = array(); foreach ( $values as $field => $value ) { if ( ! in_array( $field, array( 'external_ids', 'additional_metadata' ), true ) ) { $provenance[ $field ] = array( 'hash' => OpenImport::hash( $value ), 'source' => $source ); } }
                $values['additional_metadata'] = array( 'training_catalog' => array( 'version' => 1, 'source_id' => $row['id'], 'rank' => $row['rank'], 'wikipedia_editions' => $row['coverage'], 'year' => $row['year'] ?? null ), 'open_import' => array( 'fields' => $provenance ) );
                $r = Database::transaction( static function () use ( $store, $row, $values, $sites ) {
                    $created = $store->create( $row['type'], $values ); global $wpdb;
                    foreach ( $sites as $site ) { Database::check( $wpdb->insert( Database::table( 'maps' ), array( 'record_id' => $created['id'], 'site_id' => (int) $site->blog_id, 'status' => 'draft' ) ) ); }
                    foreach ( $sites as $site ) { Plugin::materializer()->sync( (int) $created['id'], (int) $site->blog_id ); } return $created;
                } ); $status = 'Created draft';
            }
            Database::lock( 'training-members', static function () use ( $row, $r ) { wp_cache_delete( get_current_network_id() . ':msc_training_catalog_records', 'site-options' ); $members = get_network_option( get_current_network_id(), 'msc_training_catalog_records', array() ); $members[ $row['id'] ] = $r['uuid']; update_network_option( get_current_network_id(), 'msc_training_catalog_records', $members ); } );
            return array( 'id' => $row['id'], 'uuid' => $r['uuid'], 'title' => $row['fields']['title'], 'status' => $status );
        } ); } finally { restore_current_blog(); }
    }
    public static function start(): string {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Network training-import permission is required.' ); }
        self::sites( 'msc_movie' ); self::sites( 'msc_show' ); self::dataset(); $token = wp_generate_uuid4();
        set_site_transient( 'msc_training_job_' . $token, array( 'owner' => array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ), 'index' => 0, 'dataset_hash' => hash_file( 'sha256', dirname( MSC_FILE ) . '/data/training-catalog.json' ), 'results' => array() ), DAY_IN_SECONDS ); return $token;
    }
    public static function job( string $token ): array {
        if ( ! self::allowed() || ! wp_is_uuid( $token ) ) { throw new \RuntimeException( 'Invalid or unauthorized training import.' ); }
        $job = get_site_transient( 'msc_training_job_' . $token ); if ( ! is_array( $job ) || $job['owner'] !== array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ) ) { throw new \RuntimeException( 'This import expired or belongs to another user/site.' ); } return $job;
    }
    public static function step( string $token ): array {
        return Database::lock( 'training-job:' . $token, static function () use ( $token ) {
            $job = self::job( $token ); if ( ( $job['dataset_hash'] ?? '' ) !== hash_file( 'sha256', dirname( MSC_FILE ) . '/data/training-catalog.json' ) ) { throw new \RuntimeException( 'The bundled dataset changed. Start a new import; existing titles will be reused.' ); } $rows = self::dataset()['items']; $end = min( 500, $job['index'] + 10 );
            while ( $job['index'] < $end ) { $row = $rows[ $job['index'] ]; try { $job['results'][] = self::import_row( $row ); } catch ( \Throwable $e ) { $job['results'][] = array( 'id' => $row['id'], 'title' => $row['fields']['title'], 'status' => 'Needs review: ' . $e->getMessage() ); } ++$job['index']; }
            set_site_transient( 'msc_training_job_' . $token, $job, DAY_IN_SECONDS ); return $job;
        } );
    }
    public static function page(): void {
        if ( ! self::allowed() ) { wp_die( 'Network training-import permission is required.', 'Training Catalog', array( 'response' => 403 ) ); }
        try { self::sites( 'msc_movie' ); self::sites( 'msc_show' ); } catch ( \RuntimeException $e ) { echo '<div class="wrap"><h1>Training Catalog</h1><p>' . esc_html( $e->getMessage() ) . '</p></div>'; return; }
        echo '<div class="wrap"><h1>Training Catalog</h1><p>250 Movies and 250 TV Shows with real source identities, descriptions, genres and available dates. Popularity is approximated by Wikipedia language coverage within a documented candidate sample, rather than an official all-time ranking. Facts use Wikidata CC0; no live API or subscription is required.</p><p>New records are drafts on participating sites. Existing content, publication states and local overrides are retained. Possible duplicate titles are reported for review.</p><p><a class="button" href="' . esc_url( get_admin_url( (int) self::sites( 'msc_movie' )[0]->blog_id, 'edit.php?post_type=msc_movie&msc_training=1' ) ) . '">Training Movies</a> <a class="button" href="' . esc_url( get_admin_url( (int) self::sites( 'msc_show' )[0]->blog_id, 'edit.php?post_type=msc_show&msc_training=1' ) ) . '">Training TV Shows</a></p>';
        $token = is_string( $_GET['job'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
        if ( ! $token ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_training_import">'; wp_nonce_field( 'msc_training_import' ); submit_button( 'Import 250 Movies and 250 TV Shows' ); echo '</form>'; }
        else { try { $job = self::job( $token ); echo '<section id="msc-training-import" data-endpoint="' . esc_url( rest_url( 'msc/v1/training-import/' . $token ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"><p class="msc-training-status" role="status">' . (int) $job['index'] . ' / 500 processed</p><progress max="500" value="' . (int) $job['index'] . '"></progress><p><button class="button button-primary" data-training-start>Start / resume import</button> <button class="button" data-training-pause>Pause</button></p><ul class="msc-training-results"></ul></section>'; } catch ( \Throwable $e ) { echo '<p>' . esc_html( $e->getMessage() ) . '</p>'; } }
        echo '</div>';
    }
    public static function boot(): void {
        add_action( 'admin_menu', static function () { if ( self::allowed() ) { add_submenu_page( 'msc', 'Training Catalog', 'Training Catalog', 'manage_network_options', 'msc-training', array( self::class, 'page' ) ); } } );
        add_action( 'admin_post_msc_training_import', static function () { check_admin_referer( 'msc_training_import' ); try { $token = self::start(); wp_safe_redirect( add_query_arg( array( 'page' => 'msc-training', 'job' => $token ), admin_url( 'admin.php' ) ) ); exit; } catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Training Catalog', array( 'response' => 403 ) ); } } );
        add_action( 'admin_enqueue_scripts', static function () { if ( 'msc-training' === ( $_GET['page'] ?? '' ) ) { wp_enqueue_script( 'msc-training', plugins_url( 'assets/training-catalog.js', MSC_FILE ), array(), MSC_VERSION, true ); } } );
        add_action( 'restrict_manage_posts', static function ( $type ) { if ( ! in_array( $type, array( 'msc_movie', 'msc_show' ), true ) ) { return; } echo '<select name="msc_training" aria-label="Training catalog"><option value="">All titles</option><option value="1"' . selected( '1', is_string( $_GET['msc_training'] ?? null ) ? $_GET['msc_training'] : '', false ) . '>Training catalog</option></select>'; } );
        add_action( 'pre_get_posts', static function ( $query ) {
            if ( ! is_admin() || ! $query->is_main_query() || ! in_array( $query->get( 'post_type' ), array( 'msc_movie', 'msc_show' ), true ) || '1' !== ( $_GET['msc_training'] ?? '' ) ) { return; }
            global $wpdb; $members = array_values( get_network_option( get_current_network_id(), 'msc_training_catalog_records', array() ) ); $ids = array();
            if ( $members ) { $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT m.post_id FROM ' . Database::table( 'maps' ) . ' m INNER JOIN ' . Database::table( 'records' ) . ' r ON r.id=m.record_id WHERE m.site_id=%d AND r.uuid IN (' . implode( ',', array_fill( 0, count( $members ), '%s' ) ) . ')', get_current_blog_id(), ...$members ) ); }
            $query->set( 'post__in', $ids ?: array( 0 ) );
        } );
        add_action( 'rest_api_init', static function () { register_rest_route( 'msc/v1', '/training-import/(?P<token>[a-f0-9-]{36})', array( 'methods' => 'POST', 'permission_callback' => array( self::class, 'allowed' ), 'callback' => static function ( $request ) { try { $job = self::step( $request['token'] ); return array( 'index' => $job['index'], 'results' => $job['results'] ); } catch ( \Throwable $e ) { return new \WP_Error( 'msc_training', $e->getMessage(), array( 'status' => 403 ) ); } } ) ); } );
    }
}
