<?php
namespace MSC;

/** Exact-identity seed selection with complete live enrichment and safe legacy replacement. */
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
    /** Ownership alone is insufficient: edited records are retained for manual review. */
    public static function replaceable( array $r ): bool {
        $meta = $r['data']['additional_metadata'] ?? array(); $mark = $meta['training_catalog'] ?? array();
        if ( 'trash' === $r['state'] || 'archived' === $r['state'] && empty( $mark['retired_for_rebuild'] ) ) { return false; }
        if ( 1 !== (int) ( $mark['version'] ?? 0 ) || empty( $mark['source_id'] ) || array_diff( array_keys( $meta ), array( 'training_catalog', 'open_import' ) ) ) { return false; }
        if ( ( $r['data']['external_ids']['wikidata'] ?? '' ) !== $mark['source_id'] ) { return false; }
        static $seeds; $seeds ??= array_column( self::dataset()['items'], null, 'id' ); $seed = $seeds[ $mark['source_id'] ] ?? null; $baseline = array();
        if ( $seed ) {
            if ( ( $r['data']['external_ids'] ?? array() ) != $seed['fields']['external_ids'] ) { return false; }
            $schema = Schema::get( $r['type'] ); $canonical = Schema::defaults()[ $r['type'] ]['fields'];
            foreach ( $seed['fields'] as $key => $value ) { $dest = $key; if ( isset( $canonical[ $key ]['property'] ) ) { foreach ( $schema['fields'] as $name => $field ) { if ( ( $field['property'] ?? '' ) === $canonical[ $key ]['property'] ) { $dest = $name; break; } } } if ( isset( $schema['fields'][ $dest ] ) ) { try { $baseline[ $dest ] = Schema::sanitize( $schema['fields'][ $dest ], $value ); } catch ( \InvalidArgumentException $e ) { continue; } } }
        }
        foreach ( $r['data'] as $key => $value ) {
            if ( in_array( $key, array( 'additional_metadata', 'external_ids' ), true ) || null === $value || '' === $value || array() === $value ) { continue; }
            if ( $seed && ( ! array_key_exists( $key, $baseline ) || OpenImport::hash( $baseline[ $key ] ) !== OpenImport::hash( $value ) ) ) { return false; }
            if ( ( $meta['open_import']['fields'][ $key ]['hash'] ?? '' ) !== OpenImport::hash( $value ) ) { return false; }
        }
        global $wpdb;
        return ! $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . Database::table( 'overrides' ) . ' WHERE record_id=%d LIMIT 1', $r['id'] ) );
    }
    /** Remove only our untouched version-one stubs from every local/public catalog. */
    public static function retire_legacy(): int {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Network training-import permission is required.' ); }
        global $wpdb; $ids = $wpdb->get_col( 'SELECT id FROM ' . Database::table( 'records' ) . " WHERE type IN ('msc_movie','msc_show') AND state='active' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.additional_metadata.training_catalog.version'))='1'" ); $count = 0;
        foreach ( $ids as $id ) {
            $r = Plugin::store()->get( (int) $id ); if ( ! self::replaceable( $r ) ) { continue; }
            $sites = self::sites( $r['type'] ); switch_to_blog( (int) $sites[0]->blog_id );
            try { Database::lock( 'training:' . $r['type'] . ':' . $r['data']['additional_metadata']['training_catalog']['source_id'], static function () use ( $id, &$count ) {
                $r = Plugin::store()->get( (int) $id ); if ( ! self::replaceable( $r ) || 'active' !== $r['state'] ) { return; }
                $meta = $r['data']['additional_metadata']; $meta['training_catalog']['retired_for_rebuild'] = true; Plugin::store()->update( (int) $id, array( 'additional_metadata' => $meta ), (int) $r['version'], 'archived' );
                global $wpdb; foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT site_id FROM ' . Database::table( 'maps' ) . ' WHERE record_id=%d', $id ) ) as $site ) { Plugin::materializer()->sync( (int) $id, (int) $site ); } ++$count;
            } ); } finally { restore_current_blog(); }
        } return $count;
    }
    private static function draft_mappings( array $record ): void {
        global $wpdb;
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'archived' => 0, 'spam' => 0 ) ) as $site ) {
            if ( Schema::enabled( $record['type'], (int) $site->blog_id ) && ! Plugin::store()->mapping( (int) $record['id'], (int) $site->blog_id ) ) { Database::check( $wpdb->insert( Database::table( 'maps' ), array( 'record_id' => $record['id'], 'site_id' => (int) $site->blog_id, 'status' => 'draft' ) ) ); }
        }
    }
    private static function company( array $company ): array {
        return Database::lock( 'training-company:' . $company['type'] . ':' . $company['id'], static function () use ( $company ) {
            global $wpdb; $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . " WHERE type=%s AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.wikidata'))=%s LIMIT 2", $company['type'], $company['id'] ) );
            if ( count( $ids ) > 1 ) { throw new \RuntimeException( 'Duplicate company source identities need review.' ); }
            if ( $ids ) { $r = Plugin::store()->get( (int) $ids[0] ); if ( 'active' !== $r['state'] ) { throw new \RuntimeException( 'An archived company was retained.' ); } return $r; }
            $matches = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . " WHERE type=%s AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(data,'$.title'))))=%s LIMIT 2", $company['type'], mb_strtolower( $company['name'] ) ) );
            if ( $matches ) { $r = Plugin::store()->get( (int) $matches[0] ); if ( count( $matches ) > 1 || 'active' !== $r['state'] || ! empty( $r['data']['external_ids']['wikidata'] ) && $r['data']['external_ids']['wikidata'] !== $company['id'] ) { throw new \RuntimeException( 'Company identity needs review: ' . $company['name'] ); } return $r; }
            $created = Plugin::store()->create( $company['type'], array( 'title' => $company['name'], 'external_ids' => array( 'wikidata' => $company['id'] ) ) ); self::draft_mappings( $created ); return $created;
        } );
    }
    public static function import_row( array $row ): array {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Network training-import permission is required.' ); }
        $sites = self::sites( $row['type'] ); switch_to_blog( (int) $sites[0]->blog_id ); Plugin::register_posts();
        try { return Database::lock( 'training:' . $row['type'] . ':' . $row['id'], static function () use ( $row, $sites ) {
            $store = Plugin::store(); $existing = self::existing( $row ); $owned = $existing && self::replaceable( $existing );
            if ( $existing && ! $owned ) {
                if ( 'active' !== $existing['state'] ) { throw new \RuntimeException( 'An archived or trashed record was retained.' ); }
                $status = 2 === (int) ( $existing['data']['additional_metadata']['training_catalog']['version'] ?? 0 ) ? 'Retained complete record' : 'Retained existing or edited record'; $r = $existing;
            } else {
                $schema = Schema::get( $row['type'] );
                foreach ( array( 'poster' => 'media', 'featured_image' => 'media', 'external_ids' => 'json', 'additional_metadata' => 'json', 'actors' => 'relationship_list' ) as $key => $kind ) { if ( $kind !== ( $schema['fields'][ $key ]['type'] ?? '' ) ) { throw new \RuntimeException( 'The full importer requires the ' . $key . ' field with its default type.' ); } }
                $source = TrainingSources::fetch( $row ); $media = array(); $committed = false;
                try {
                    foreach ( array( 'poster', 'featured' ) as $kind ) {
                        $url = $source[ $kind . '_url' ];
                        if ( isset( $media[ $url ] ) ) { continue; }
                        $media[ $url ] = MediaImport::import( $url, $source['fields']['title'] . ' — ' . $kind, 'https://www.wikidata.org/wiki/' . $row['id'], $source['image_credit'] );
                    }
                    $r = PersonIdentity::locked( static function () use ( $row, $sites, $source, $media, $existing, $schema, $store ) {
                        return Database::transaction( static function () use ( $row, $sites, $source, $media, $existing, $schema, $store ) {
                            $values = $source['fields']; $values['external_ids'] = $source['external_ids']; $values['poster'] = $media[ $source['poster_url'] ]; $values['featured_image'] = $media[ $source['featured_url'] ];
                            $cast = $crew = 0;
                            foreach ( $source['credits'] as $credit ) {
                                $roles = array_values( array_filter( $credit['roles'], static fn( $role ) => 'relationship_list' === ( $schema['fields'][ $role ]['type'] ?? '' ) && 'msc_person' === ( $schema['fields'][ $role ]['target'] ?? '' ) ) ); if ( ! $roles ) { continue; }
                                $match = PersonIdentity::assess( $credit['name'], PersonIdentity::credit_ids( $credit ), $credit['fields']['birth_date'] ?? '' );
                                $choice = ! $match['strong'] && 1 === count( $match['possible'] ) ? (string) array_key_first( $match['possible'] ) : 'auto';
                                $person = OpenImport::credit( $credit, $choice ); if ( ! $match['strong'] && ! $match['possible'] ) { self::draft_mappings( $person ); }
                                foreach ( $roles as $role ) { $values[ $role ][] = $person['uuid']; if ( 'actors' === $role ) { ++$cast; } else { ++$crew; } }
                            }
                            if ( ! $cast || ! $crew ) { throw new \RuntimeException( 'The configured schema cannot retain the supplied cast and crew.' ); }
                            foreach ( $source['companies'] as $company ) { if ( isset( $schema['fields'][ $company['field'] ] ) && Schema::enabled( $company['type'] ) ) { $entity = self::company( $company ); $values[ $company['field'] ][] = $entity['uuid']; } }
                            $canonical = Schema::defaults()[ $row['type'] ]['fields']; $fit = array();
                            foreach ( $values as $key => $value ) { $dest = $key; if ( isset( $canonical[ $key ]['property'] ) ) { foreach ( $schema['fields'] as $name => $field ) { if ( ( $field['property'] ?? '' ) === $canonical[ $key ]['property'] ) { $dest = $name; break; } } } if ( isset( $schema['fields'][ $dest ] ) ) { $fit[ $dest ] = Schema::sanitize( $schema['fields'][ $dest ], $value ); } }
                            $provenance = array(); $default_source = OpenSources::source( 'wikidata', 'https://www.wikidata.org/wiki/' . $row['id'] );
                            foreach ( $fit as $field => $value ) { $provenance[ $field ] = array( 'hash' => OpenImport::hash( $value ), 'source' => $source['field_sources'][ $field ] ?? $default_source ); }
                            $fit['additional_metadata'] = array( 'training_catalog' => array( 'version' => 2, 'source_id' => $row['id'], 'rank' => $row['rank'], 'year' => $row['year'] ?? null, 'completed_at' => gmdate( 'c' ), 'warnings' => $source['warnings'], 'artwork' => $source['poster_url'] === $source['featured_url'] ? 'Source poster used for both image fields.' : 'Source poster and background.' ), 'open_import' => array( 'fields' => $provenance ) );
                            if ( ! empty( $source['omdb'] ) ) { $fit['additional_metadata']['omdb'] = $source['omdb']; }
                            $r = $existing ? $store->update( (int) $existing['id'], $fit, (int) $existing['version'], 'active' ) : $store->create( $row['type'], $fit ); global $wpdb;
                            foreach ( $sites as $site ) {
                                $map = $store->mapping( (int) $r['id'], (int) $site->blog_id );
                                if ( ! $map ) { Database::check( $wpdb->insert( Database::table( 'maps' ), array( 'record_id' => $r['id'], 'site_id' => (int) $site->blog_id, 'status' => 'draft' ) ) ); }
                                elseif ( ! $map['suppressed'] ) { Database::check( $wpdb->update( Database::table( 'maps' ), array( 'status' => 'draft', 'local_version' => (int) $map['local_version'] + 1 ), array( 'record_id' => $r['id'], 'site_id' => (int) $site->blog_id ) ) ); }
                                Plugin::materializer()->sync( (int) $r['id'], (int) $site->blog_id );
                            } return $r;
                        } );
                    } ); $committed = true; $status = $existing ? 'Rebuilt complete draft' : 'Created complete draft';
                } finally { if ( ! $committed ) { foreach ( $media as $ref ) { wp_delete_attachment( $ref['attachment_id'], true ); } } }
            }
            Database::lock( 'training-members', static function () use ( $row, $r ) { wp_cache_delete( get_current_network_id() . ':msc_training_catalog_records', 'site-options' ); $members = get_network_option( get_current_network_id(), 'msc_training_catalog_records', array() ); $members[ $row['id'] ] = $r['uuid']; update_network_option( get_current_network_id(), 'msc_training_catalog_records', $members ); } );
            return array( 'id' => $row['id'], 'uuid' => $r['uuid'], 'title' => $row['fields']['title'], 'status' => $status );
        } ); } finally { restore_current_blog(); }
    }
    public static function start(): string {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Network training-import permission is required.' ); }
        self::sites( 'msc_movie' ); self::sites( 'msc_show' ); self::dataset(); self::retire_legacy(); $token = wp_generate_uuid4();
        set_site_transient( 'msc_training_job_' . $token, array( 'owner' => array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ), 'index' => 0, 'dataset_hash' => hash_file( 'sha256', dirname( MSC_FILE ) . '/data/training-catalog.json' ), 'results' => array() ), DAY_IN_SECONDS ); return $token;
    }
    public static function job( string $token ): array {
        if ( ! self::allowed() || ! wp_is_uuid( $token ) ) { throw new \RuntimeException( 'Invalid or unauthorized training import.' ); }
        $job = get_site_transient( 'msc_training_job_' . $token ); if ( ! is_array( $job ) || $job['owner'] !== array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ) ) { throw new \RuntimeException( 'This import expired or belongs to another user/site.' ); } return $job;
    }
    public static function step( string $token ): array {
        return Database::lock( 'training-job:' . $token, static function () use ( $token ) {
            $job = self::job( $token ); if ( ( $job['dataset_hash'] ?? '' ) !== hash_file( 'sha256', dirname( MSC_FILE ) . '/data/training-catalog.json' ) ) { throw new \RuntimeException( 'The bundled dataset changed. Start a new import; existing titles will be reused.' ); } $rows = self::dataset()['items']; $end = min( 500, $job['index'] + 1 );
            unset( $job['retry_after'] );
            while ( $job['index'] < $end ) { $row = $rows[ $job['index'] ]; try { $job['results'][] = self::import_row( $row ); } catch ( \Throwable $e ) { if ( $e instanceof SourceError && 429 === $e->status ) { $job['retry_after'] = 15; break; } $job['results'][] = array( 'id' => $row['id'], 'title' => $row['fields']['title'], 'status' => 'Needs review: ' . $e->getMessage() ); } ++$job['index']; }
            set_site_transient( 'msc_training_job_' . $token, $job, DAY_IN_SECONDS ); return $job;
        } );
    }
    public static function page(): void {
        if ( ! self::allowed() ) { wp_die( 'Network training-import permission is required.', 'Training Catalog', array( 'response' => 403 ) ); }
        try { self::sites( 'msc_movie' ); self::sites( 'msc_show' ); } catch ( \RuntimeException $e ) { echo '<div class="wrap"><h1>Training Catalog</h1><p>' . esc_html( $e->getMessage() ) . '</p></div>'; return; }
        echo '<div class="wrap"><h1>Training Catalog</h1><p>Rebuild 250 Movies and 250 TV Shows with full descriptions, linked cast and crew, genres, dates, posters and featured images. Data comes from Wikidata, Wikipedia, TVmaze and your configured OMDb key. Internet access is required. Incomplete source records are reported for retry and never added as partial entries.</p><p>Starting a rebuild removes untouched version-one training stubs from the catalogs. Complete replacements are drafts for review. Your own records and any training records you edited are retained. Existing global identities are preserved. One real source poster may fill both image fields when a separate background is unavailable.</p><p><a class="button" href="' . esc_url( get_admin_url( (int) self::sites( 'msc_movie' )[0]->blog_id, 'edit.php?post_type=msc_movie&msc_training=1' ) ) . '">Training Movies</a> <a class="button" href="' . esc_url( get_admin_url( (int) self::sites( 'msc_show' )[0]->blog_id, 'edit.php?post_type=msc_show&msc_training=1' ) ) . '">Training TV Shows</a></p>';
        $token = is_string( $_GET['job'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
        if ( ! $token ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_training_import">'; wp_nonce_field( 'msc_training_import' ); submit_button( 'Rebuild complete training catalog' ); echo '</form>'; }
        else { try { $job = self::job( $token ); echo '<section id="msc-training-import" data-endpoint="' . esc_url( rest_url( 'msc/v1/training-import/' . $token ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"><p class="msc-training-status" role="status">' . (int) $job['index'] . ' / 500 processed</p><progress max="500" value="' . (int) $job['index'] . '"></progress><p><button class="button button-primary" data-training-start>Start / resume import</button> <button class="button" data-training-pause>Pause</button></p><ul class="msc-training-results"></ul></section>'; } catch ( \Throwable $e ) { echo '<p>' . esc_html( $e->getMessage() ) . '</p>'; } }
        echo '</div>';
    }
    public static function boot(): void {
        add_action( 'admin_menu', static function () { if ( self::allowed() ) { add_submenu_page( 'msc', 'Training Catalog', 'Training Catalog', 'manage_network_options', 'msc-training', array( self::class, 'page' ) ); } } );
        add_action( 'admin_post_msc_training_import', static function () { check_admin_referer( 'msc_training_import' ); try { $token = self::start(); wp_safe_redirect( add_query_arg( array( 'page' => 'msc-training', 'job' => $token ), admin_url( 'admin.php' ) ) ); exit; } catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Training Catalog', array( 'response' => 403 ) ); } } );
        add_action( 'admin_enqueue_scripts', static function () { if ( 'msc-training' === ( $_GET['page'] ?? '' ) ) { wp_enqueue_script( 'msc-training', plugins_url( 'assets/training-catalog.js', MSC_FILE ), array(), MSC_VERSION, true ); } } );
        add_action( 'restrict_manage_posts', static function ( $type ) { if ( ! in_array( $type, array( 'msc_movie', 'msc_show' ), true ) ) { return; } echo '<select name="msc_training" aria-label="Training catalog"><option value="">All titles</option><option value="1"' . selected( '1', is_string( $_GET['msc_training'] ?? null ) ? $_GET['msc_training'] : '', false ) . '>Training catalog</option></select>'; } );
        add_action( 'pre_get_posts', static function ( $query ) {
            if ( ! is_admin() || ! $query->is_main_query() || ! in_array( $query->get( 'post_type' ), array( 'msc_movie', 'msc_show' ), true ) ) { return; }
            global $wpdb;
            $hidden = $wpdb->get_col( $wpdb->prepare( 'SELECT m.post_id FROM ' . Database::table( 'maps' ) . ' m INNER JOIN ' . Database::table( 'records' ) . " r ON r.id=m.record_id WHERE m.site_id=%d AND r.type=%s AND r.state='archived' AND JSON_EXTRACT(r.data,'$.additional_metadata.training_catalog.retired_for_rebuild')=true AND m.post_id IS NOT NULL", get_current_blog_id(), $query->get( 'post_type' ) ) );
            $query->set( 'post__not_in', array_values( array_unique( array_merge( (array) $query->get( 'post__not_in' ), array_map( 'intval', $hidden ) ) ) ) );
            if ( '1' !== ( $_GET['msc_training'] ?? '' ) ) { return; }
             $members = array_values( array_intersect_key( get_network_option( get_current_network_id(), 'msc_training_catalog_records', array() ), array_fill_keys( array_column( self::dataset()['items'], 'id' ), true ) ) ); $ids = array();
            if ( $members ) { $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT m.post_id FROM ' . Database::table( 'maps' ) . ' m INNER JOIN ' . Database::table( 'records' ) . ' r ON r.id=m.record_id WHERE m.site_id=%d AND r.uuid IN (' . implode( ',', array_fill( 0, count( $members ), '%s' ) ) . ')', get_current_blog_id(), ...$members ) ); }
            $query->set( 'post__in', $ids ?: array( 0 ) );
        } );
        add_action( 'rest_api_init', static function () { register_rest_route( 'msc/v1', '/training-import/(?P<token>[a-f0-9-]{36})', array( 'methods' => 'POST', 'permission_callback' => array( self::class, 'allowed' ), 'callback' => static function ( $request ) { try { $job = self::step( $request['token'] ); return array( 'index' => $job['index'], 'results' => $job['results'], 'retry_after' => $job['retry_after'] ?? 0 ); } catch ( \Throwable $e ) { return new \WP_Error( 'msc_training', $e->getMessage(), array( 'status' => 403 ) ); } } ) ); } );
    }
}
