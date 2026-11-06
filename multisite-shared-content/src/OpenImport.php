<?php
namespace MSC;

/** Reviewed global imports; local overrides and publication settings remain authoritative. */
final class OpenImport {
    public const TYPES = array( 'msc_movie' => 'Movies', 'msc_show' => 'TV Shows', 'msc_episode' => 'Episodes', 'msc_person' => 'People', 'msc_game' => 'Video Games' );
    public static function allowed(): bool {
        return ( current_user_can( 'msc_create_global' ) || current_user_can( 'msc_edit_global' ) ) && (bool) array_filter( array_keys( self::TYPES ), static fn( $type ) => isset( Schema::all()[ $type ] ) && Schema::enabled( $type ) );
    }
    public static function matches( string $type, array $ids ): array {
        global $wpdb; $clauses = array(); $params = array( $type );
        foreach ( array( 'wikidata', 'tvmaze', 'imdb', 'igdb' ) as $provider ) {
            if ( ! isset( $ids[ $provider ] ) || '' === (string) $ids[ $provider ] ) { continue; }
            $clauses[] = "JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids." . $provider . "'))=%s"; $params[] = (string) $ids[ $provider ];
        }
        if ( ! $clauses ) { return array(); }
        $found = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . ' WHERE type=%s AND (' . implode( ' OR ', $clauses ) . ') ORDER BY id LIMIT 3', ...$params ) );
        return array_map( static fn( $id ) => Plugin::store()->get( (int) $id ), $found );
    }
    private static function compatible( array $record, array $ids ): void {
        if ( 'active' !== $record['state'] ) { throw new SourceError( 'The matching record is archived or trashed. Review it first.', 409 ); }
        foreach ( $ids as $provider => $id ) { if ( isset( $record['data']['external_ids'][ $provider ] ) && (string) $record['data']['external_ids'][ $provider ] !== (string) $id ) { throw new SourceError( 'The destination identifies a different ' . $provider . ' record. Review the identifiers first.', 409 ); } }
    }
    private static function parent( string $uuid, string $tvmaze ): array {
        $r = $uuid ? Plugin::store()->get( $uuid ) : null;
        if ( ! $r || 'msc_show' !== $r['type'] || 'active' !== $r['state'] || ! Schema::enabled( 'msc_show' ) || (string) ( $r['data']['external_ids']['tvmaze'] ?? '' ) !== $tvmaze ) { throw new SourceError( 'Import or connect the matching TV Show first, then select it as this Episode’s parent.' ); }
        return $r;
    }
    private static function fit( string $type, array $fields, array &$warnings ): array {
        $schema = Schema::get( $type ); $canonical = Schema::defaults()[ $type ]['fields']; $result = array();
        foreach ( $fields as $key => $value ) {
            $destination = $key;
            if ( isset( $canonical[ $key ]['property'] ) ) { foreach ( $schema['fields'] as $other => $definition ) { if ( ( $definition['property'] ?? '' ) === $canonical[ $key ]['property'] ) { $destination = $other; break; } } }
            if ( ! isset( $schema['fields'][ $destination ] ) ) { continue; }
            try { $result[ $destination ] = Schema::sanitize( $schema['fields'][ $destination ], $value ); }
            catch ( \Throwable $e ) { $warnings[] = 'Skipped a source value incompatible with your field configuration: ' . $destination; }
        }
        return $result;
    }
    private static function validate_type( string $type ): void {
        if ( ! isset( self::TYPES[ $type ] ) || ! self::allowed() || ! Schema::enabled( $type ) ) { throw new SourceError( 'Global editing permission and an enrolled content type are required.', 403 ); }
        $fields = Schema::get( $type )['fields'];
        if ( 'json' !== ( $fields['external_ids']['type'] ?? '' ) || 'json' !== ( $fields['additional_metadata']['type'] ?? '' ) ) { throw new SourceError( 'This type needs external_ids and additional_metadata JSON fields to retain identity and attribution.' ); }
        if ( 'msc_episode' === $type && ( 'relationship' !== ( $fields['parent_show']['type'] ?? '' ) || 'msc_show' !== ( $fields['parent_show']['target'] ?? '' ) ) ) { throw new SourceError( 'Episodes require a parent_show relationship to TV Shows.' ); }
    }
    public static function preview( string $provider, string $id, string $type, string $target = '', string $parent = '' ): array {
        self::validate_type( $type ); $s = OpenSources::details( $provider, $id, $type );
        $matches = self::matches( $type, $s['external_ids'] );
        if ( count( $matches ) > 1 ) { throw new SourceError( 'Source IDs identify multiple shared records. Resolve these before importing.', 409 ); }
        $person_match = 'msc_person' === $type ? PersonIdentity::assess( $s['fields']['title'] ?? '', $s['external_ids'], $s['fields']['birth_date'] ?? '' ) : array( 'strong' => array(), 'possible' => array() );
        $r = $target ? Plugin::store()->get( $target ) : ( $matches[0] ?? ( $person_match['strong'] ? Plugin::store()->get( array_key_first( $person_match['strong'] ) ) : null ) );
        if ( $target && ! $r || $r && $r['type'] !== $type || $r && $matches && $matches[0]['uuid'] !== $r['uuid'] ) { throw new SourceError( 'The destination is unavailable or another shared record already uses this identity.', 409 ); }
        if ( $r ) { self::compatible( $r, $s['external_ids'] ); }
        Permissions::require( $r ? 'msc_edit_global' : 'msc_create_global', $type );
        if ( 'msc_episode' === $type ) {
            if ( ! $parent && $r ) { $parent = $r['data']['parent_show'] ?? ''; }
            if ( ! $parent ) { $shows = self::matches( 'msc_show', array( 'tvmaze' => $s['parent_tvmaze'] ) ); if ( 1 === count( $shows ) ) { $parent = $shows[0]['uuid']; } }
            $show = self::parent( $parent, $s['parent_tvmaze'] ); $s['fields']['parent_show'] = $show['uuid'];
            if ( $r && ! empty( $r['data']['parent_show'] ) && $r['data']['parent_show'] !== $show['uuid'] ) { throw new SourceError( 'This Episode is linked to another Show. Correct that relationship before importing.', 409 ); }
        }
        $original = $s['fields']; $s['fields'] = self::fit( $type, $original, $s['warnings'] );
        // Keep source associations when a network schema renames a native property field.
        foreach ( Schema::defaults()[ $type ]['fields'] as $key => $definition ) { if ( ! isset( $definition['property'], $original[ $key ] ) ) { continue; } foreach ( Schema::get( $type )['fields'] as $dest => $field ) { if ( ( $field['property'] ?? '' ) === $definition['property'] && isset( $s['field_sources'][ $key ] ) ) { $s['field_sources'][ $dest ] = $s['field_sources'][ $key ]; } } }
        $s['roles'] = array();
        if ( Schema::enabled( 'msc_person' ) ) { foreach ( $s['credits'] as $credit ) { foreach ( $credit['roles'] as $role ) { $field = Schema::get( $type )['fields'][ $role ] ?? array(); if ( 'relationship_list' === ( $field['type'] ?? '' ) && 'msc_person' === ( $field['target'] ?? '' ) ) { $s['roles'][ $role ][] = $credit['name']; } } } }
        $s['person_matches'] = PersonIdentity::review( $s['credits'] ); $s['person_candidates'] = ! $r ? $person_match['possible'] : array();
        $s += array( 'target' => $r['uuid'] ?? '', 'version' => (int) ( $r['version'] ?? 0 ) );
        $s['owner'] = array( get_current_network_id(), get_current_blog_id(), get_current_user_id() );
        $s['token'] = wp_generate_uuid4(); set_site_transient( 'msc_open_preview_' . $s['token'], $s, 30 * MINUTE_IN_SECONDS ); return $s;
    }
    private static function snapshot( string $token, string $kind ): array {
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) { throw new SourceError( 'Open a fresh import preview.' ); }
        $s = OpenClient::fresh( 'msc_open_' . $kind . '_' . $token );
        if ( ! is_array( $s ) || ( $s['owner'] ?? array() ) !== array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ) ) { throw new SourceError( 'This preview expired or belongs to another user or site. Search again.', 403 ); } return $s;
    }
    private static function locked( array $ids, callable $callback ) {
        $keys = array(); foreach ( $ids as $provider => $values ) { foreach ( (array) $values as $id ) { $keys[] = 'open:identity:' . $provider . ':' . $id; } } $keys = array_values( array_unique( $keys ) ); sort( $keys );
        $lock = static function ( int $i ) use ( &$lock, $keys, $callback ) { return isset( $keys[ $i ] ) ? Database::lock( $keys[ $i ], static fn() => $lock( $i + 1 ) ) : $callback(); }; return $lock( 0 );
    }
    public static function hash( $value ): string { return hash( 'sha256', wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); }
    private static function provenance( array $data, array $values, array $s ): array {
        $metadata = (array) ( $data['additional_metadata'] ?? array() ); $open = (array) ( $metadata['open_import'] ?? array() );
        foreach ( $values as $key => $value ) { if ( 'additional_metadata' === $key || 'external_ids' === $key ) { continue; } $open['fields'][ $key ] = array( 'hash' => self::hash( $value ), 'source' => $s['field_sources'][ $key ] ?? $s['source'] ); }
        $open['last_import'] = array( 'provider' => $s['provider'], 'id' => $s['id'], 'at' => gmdate( 'c' ), 'details' => $s['details'] ?? array() );
        $metadata['open_import'] = $open; return $metadata;
    }
    private static function image( array $image, string $title ): array {
        if ( ! current_user_can( 'upload_files' ) ) { throw new SourceError( 'Media upload permission is required.', 403 ); }
        $p = wp_parse_url( $image['url'] );
        $allowed = 'upload.wikimedia.org' === ( $p['host'] ?? '' ) || 'images.igdb.com' === ( $p['host'] ?? '' ) && preg_match( '#^/igdb/image/upload/t_cover_big_2x/[A-Za-z0-9_-]+\.jpg$#D', $p['path'] ?? '' );
        if ( 'https' !== ( $p['scheme'] ?? '' ) || ! $allowed || ! empty( $p['user'] ) || ! empty( $p['port'] ) || ! empty( $p['query'] ) ) { throw new SourceError( 'Unsupported source image URL.' ); }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = wp_tempnam( 'msc-commons' ); if ( ! $tmp ) { throw new SourceError( 'Could not prepare an image download.' ); }
        try {
            $response = wp_safe_remote_get( $image['url'], array( 'timeout' => 20, 'redirection' => 0, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 5 * MB_IN_BYTES, 'sslverify' => true ) );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $tmp ) || filesize( $tmp ) >= 5 * MB_IN_BYTES ) { throw new SourceError( 'The image could not be downloaded.' ); }
            $mime = wp_get_image_mime( $tmp ); $ext = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $mime ] ?? ''; $size = @getimagesize( $tmp );
            if ( ! $ext || ! $size || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 20000000 ) { throw new SourceError( 'The image has an unsupported format or dimensions.' ); }
            $source = $image['source']; $credit = $source['attribution'] . ' · ' . $source['license'] . ' · ' . $source['url'] . ' · ' . $source['license_url'] . ' · ' . $source['changes'];
            $attachment = media_handle_sideload( array( 'name' => sanitize_title( $title ) . '.' . $ext, 'tmp_name' => $tmp ), 0, $title, array( 'post_excerpt' => $credit, 'post_content' => $credit ) );
            if ( is_wp_error( $attachment ) ) { throw new SourceError( 'WordPress could not save the image.' ); }
            update_post_meta( $attachment, '_msc_open_source', $source ); update_post_meta( $attachment, '_wp_attachment_image_alt', $title );
            return array( 'site_id' => get_current_blog_id(), 'attachment_id' => (int) $attachment );
        } finally { if ( is_file( $tmp ) ) { wp_delete_file( $tmp ); } }
    }
    private static function person( array $credit, string $choice ): array {
        $ids = PersonIdentity::credit_ids( $credit );
        return self::locked( $ids, static function () use ( $credit, $choice, $ids ) {
            $existing = PersonIdentity::resolve( $credit['name'], $ids, $credit['fields']['birth_date'] ?? '', $choice );
            if ( $existing ) {
                self::compatible( $existing, $ids ); $values = array();
                $external = array_replace( (array) ( $existing['data']['external_ids'] ?? array() ), $ids );
                if ( $external !== ( $existing['data']['external_ids'] ?? array() ) ) { Permissions::require( 'msc_edit_global', 'msc_person' ); $values['external_ids'] = $external; }
                if ( current_user_can( 'msc_edit_global' ) && 'multiselect' === ( Schema::get( 'msc_person' )['fields']['professions']['type'] ?? '' ) ) { $professions = PersonIdentity::professions( $existing, $credit['roles'] ); if ( $professions !== ( $existing['data']['professions'] ?? array() ) ) { $values['professions'] = $professions; } }
                if ( current_user_can( 'msc_edit_global' ) && empty( $existing['data']['birth_date'] ) && ! empty( $credit['fields']['birth_date'] ) && 'date' === ( Schema::get( 'msc_person' )['fields']['birth_date']['type'] ?? '' ) ) { $values['birth_date'] = $credit['fields']['birth_date']; }
                if ( ! $values ) { return $existing; }
                $values['additional_metadata'] = self::provenance( $existing['data'], $values, $credit + array( 'field_sources' => array() ) );
                return PersonIdentity::remember( Plugin::store()->update( $existing['uuid'], $values, (int) $existing['version'] ) );
            }
            Permissions::require( 'msc_create_global', 'msc_person' ); $warnings = array(); $values = self::fit( 'msc_person', $credit['fields'], $warnings );
            $schema = Schema::get( 'msc_person' ); if ( 'json' !== ( $schema['fields']['external_ids']['type'] ?? '' ) || 'json' !== ( $schema['fields']['additional_metadata']['type'] ?? '' ) ) { throw new SourceError( 'People need identity and attribution JSON fields before importing credits.' ); }
            $professions = array(); foreach ( $credit['roles'] as $role ) { $professions[] = array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Writer', 'producers' => 'Producer', 'creators' => 'Creator' )[ $role ]; }
            if ( 'multiselect' === ( $schema['fields']['professions']['type'] ?? '' ) ) { $values['professions'] = array_values( array_unique( $professions ) ); }
            $values['external_ids'] = $ids; $values['additional_metadata'] = self::provenance( array(), $values, $credit + array( 'field_sources' => array() ) );
            return PersonIdentity::remember( Plugin::store()->create( 'msc_person', $values ) );
        } );
    }
    public static function import( array $request ): array {
        if ( ! is_string( $request['nonce'] ?? null ) || ! wp_verify_nonce( $request['nonce'], 'msc_open_import' ) ) { throw new SourceError( 'The import security token expired. Open a fresh preview.', 403 ); }
        $s = self::snapshot( is_string( $request['token'] ?? null ) ? $request['token'] : '', 'preview' );
        $selected = $request['fields'] ?? array(); $roles = $request['roles'] ?? array(); $choices = $request['people'] ?? array();
        if ( ! is_array( $choices ) || array_diff_key( $choices, $s['credits'] ) || array_filter( $choices, static fn( $value ) => ! is_string( $value ) ) || isset( $request['distinct_person'] ) && ! in_array( $request['distinct_person'], array( '', '1' ), true ) ) { throw new SourceError( 'Review the Person choices from this preview.' ); }
        if ( ! is_array( $selected ) || ! array_is_list( $selected ) || array_filter( $selected, static fn( $x ) => ! is_string( $x ) ) || array_diff( $selected, array_keys( $s['fields'] ) ) || ! is_array( $roles ) || ! array_is_list( $roles ) || array_filter( $roles, static fn( $x ) => ! is_string( $x ) ) || array_diff( $roles, array_keys( $s['roles'] ) ) ) { throw new SourceError( 'The selected fields do not match this preview.' ); }
        if ( ! $selected && ! $roles && empty( $request['image'] ) ) { throw new SourceError( 'Select at least one field to import.' ); }
        self::validate_type( $s['type'] );
        $locks = $s['external_ids']; foreach ( $s['credits'] as $credit ) { if ( array_intersect( $roles, $credit['roles'] ) ) { foreach ( PersonIdentity::credit_ids( $credit ) as $provider => $id ) { $locks[ $provider ] = array_merge( (array) ( $locks[ $provider ] ?? array() ), array( $id ) ); } } }
        return self::locked( $locks, static function () use ( $s, $selected, $roles, $choices, $request ) {
            $matches = self::matches( $s['type'], $s['external_ids'] ); $r = $s['target'] ? Plugin::store()->get( $s['target'] ) : null;
            if ( count( $matches ) > 1 || $r && $matches && $matches[0]['uuid'] !== $r['uuid'] ) { throw new SourceError( 'The source identity changed. Open a fresh preview.', 409 ); }
            if ( ! $s['target'] && $matches ) { self::compatible( $matches[0], $s['external_ids'] ); return array( 'record' => $matches[0], 'people' => array(), 'warnings' => array( 'Already imported. The existing shared record was retained.' ) ); }
            Permissions::require( $r ? 'msc_edit_global' : 'msc_create_global', $s['type'] );
            if ( $s['target'] && ( ! $r || $r['type'] !== $s['type'] || (int) $r['version'] !== $s['version'] ) ) { throw new SourceError( 'Conflict: the shared record changed. Open a fresh preview.', 409 ); }
            if ( $r ) { self::compatible( $r, $s['external_ids'] ); }
            $values = array_intersect_key( $s['fields'], array_flip( $selected ) );
            if ( 'msc_episode' === $s['type'] ) {
                $parent = $values['parent_show'] ?? $r['data']['parent_show'] ?? ''; self::parent( $parent, $s['parent_tvmaze'] );
                if ( $parent !== $s['fields']['parent_show'] ) { throw new SourceError( 'The Episode parent changed. Open a fresh preview.', 409 ); }
            }
            if ( ! $r ) { $title = false; foreach ( Schema::get( $s['type'] )['fields'] as $key => $field ) { if ( 'post_title' === ( $field['property'] ?? '' ) && '' !== (string) ( $values[ $key ] ?? '' ) ) { $title = true; } } if ( ! $title ) { throw new SourceError( 'Select the title field when creating a record.' ); } }
            $values['external_ids'] = array_replace( (array) ( $r['data']['external_ids'] ?? array() ), $s['external_ids'] );
            if ( 'msc_game' === $s['type'] && ! GamePolicy::accepts( array( 'type' => 'msc_game', 'data' => array_replace( $r['data'] ?? array(), $values ) ) ) ) { throw new SourceError( 'Select Sony platforms or Sony platform release dates when creating a game on this site.' ); }
            Schema::values( $s['type'], $values ); $warnings = $s['warnings']; $media = null; $people = array();
            if ( ! empty( $request['image'] ) ) {
                $key = 'msc_person' === $s['type'] ? 'headshot' : 'poster';
                if ( ! $s['image'] || 'media' !== ( Schema::get( $s['type'] )['fields'][ $key ]['type'] ?? '' ) ) { throw new SourceError( 'This preview has no supported licensed image.' ); }
                try { $media = self::image( $s['image'], $s['fields']['title'] ?? $s['id'] ); $values[ $key ] = $media; }
                catch ( SourceError $e ) { if ( 403 === $e->status ) { throw $e; } $warnings[] = 'Image download failed. Selected text was saved and the previous image retained. Retry from a fresh preview.'; }
            }
            try {
                $saved = PersonIdentity::locked( static function () use ( $s, $r, $values, $roles, $choices, $request, &$people ) { return Database::transaction( static function () use ( $s, $r, $values, $roles, $choices, $request, &$people ) {
                    if ( 'msc_person' === $s['type'] && ! $r ) {
                        $existing = PersonIdentity::resolve( $s['fields']['title'] ?? '', $s['external_ids'], $s['fields']['birth_date'] ?? '', ! empty( $request['distinct_person'] ) ? 'new_distinct' : 'new' );
                        if ( $existing ) { throw new SourceError( 'This Person now exists. Open a fresh preview to update the existing record.', 409 ); }
                    }
                    foreach ( $roles as $role ) { $values[ $role ] = array(); }
                    foreach ( $s['credits'] as $credit_key => $credit ) {
                        $used = array_intersect( $roles, $credit['roles'] ); if ( ! $used ) { continue; }
                        if ( 'skip' === ( $choices[ $credit_key ] ?? '' ) ) { continue; }
                        $person = self::person( $credit, $choices[ $credit_key ] ?? 'auto' ); $people[ $person['uuid'] ] = $person['uuid'];
                        foreach ( $used as $role ) { $values[ $role ][] = $person['uuid']; }
                    }
                    $values['additional_metadata'] = self::provenance( $r['data'] ?? array(), $values, $s );
                    return $r ? Plugin::store()->update( $r['uuid'], $values, $s['version'] ) : Plugin::store()->create( $s['type'], $values );
                } ); } );
            } catch ( \Throwable $e ) { if ( $media ) { wp_delete_attachment( $media['attachment_id'], true ); } throw $e; }
            foreach ( array_merge( array_values( $people ), array( $saved['uuid'] ) ) as $uuid ) { try { Propagation::send( Plugin::store()->get( $uuid ) ); } catch ( \Throwable $e ) { $warnings[] = 'Saved globally; delivery remains queued on one or more sites.'; } }
            return array( 'record' => $saved, 'people' => array_values( $people ), 'warnings' => array_values( array_unique( $warnings ) ) );
        } );
    }
    public static function batch_start( string $parent, array $episode_ids, bool $update ): string {
        self::validate_type( 'msc_episode' ); $show = Plugin::store()->get( $parent );
        $id = (string) ( $show['data']['external_ids']['tvmaze'] ?? '' ); self::parent( $parent, $id );
        if ( ! array_is_list( $episode_ids ) || ! $episode_ids || count( $episode_ids ) > 1000 || array_filter( $episode_ids, static fn( $x ) => ! is_string( $x ) ) ) { throw new SourceError( 'Select between 1 and 1,000 episodes.' ); }
        $available = array_column( OpenSources::episodes( $id ), null, 'id' ); $items = array();
        foreach ( array_unique( $episode_ids ) as $episode_id ) {
            OpenClient::numeric( $episode_id ); if ( ! isset( $available[ $episode_id ] ) ) { throw new SourceError( 'A selected episode does not belong to this Show.' ); }
            $matches = self::matches( 'msc_episode', array( 'tvmaze' => $episode_id ) );
            if ( count( $matches ) > 1 ) { throw new SourceError( 'Duplicate Episode IDs need review before this batch can run.', 409 ); }
            if ( $matches && ! $update ) { continue; }
            Permissions::require( $matches ? 'msc_edit_global' : 'msc_create_global', 'msc_episode' );
            $items[] = array( 'id' => $episode_id, 'title' => $available[ $episode_id ]['title'], 'target' => $matches[0]['uuid'] ?? '', 'version' => (int) ( $matches[0]['version'] ?? 0 ) );
        }
        if ( ! $items ) { throw new SourceError( 'All selected episodes are already imported. Enable refreshing existing source fields if you want to update them.' ); }
        $token = wp_generate_uuid4(); $job = array( 'owner' => array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ), 'parent' => $parent, 'show_id' => $id, 'items' => $items, 'index' => 0, 'results' => array() );
        set_site_transient( 'msc_open_job_' . $token, $job, DAY_IN_SECONDS ); return $token;
    }
    public static function batch( string $token ): array { return self::snapshot( $token, 'job' ); }
    public static function batch_step( string $token ): array {
        self::validate_type( 'msc_episode' );
        return Database::lock( 'open:job:' . $token, static function () use ( $token ) {
            $job = self::batch( $token ); self::parent( $job['parent'], $job['show_id'] );
            if ( $job['index'] >= count( $job['items'] ) ) { return $job; }
            $item = $job['items'][ $job['index'] ];
            try {
                if ( $item['target'] ) { $r = Plugin::store()->get( $item['target'] ); if ( ! $r || (int) $r['version'] !== $item['version'] ) { throw new SourceError( 'This Episode changed after the batch was reviewed. Preview it individually before updating.', 409 ); } }
                $s = self::preview( 'tvmaze', $item['id'], 'msc_episode', $item['target'], $job['parent'] );
                if ( $item['target'] && $s['version'] !== $item['version'] ) { throw new SourceError( 'This Episode changed during the batch. Preview it individually.', 409 ); }
                if ( ! $item['target'] && $s['target'] ) { $job['results'][] = array( 'title' => $item['title'], 'status' => 'Skipped: already imported', 'uuid' => $s['target'] ); }
                else {
                    $saved = self::import( array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $s['token'], 'fields' => array_keys( $s['fields'] ) ) );
                    $job['results'][] = array( 'title' => $item['title'], 'status' => 'Saved', 'uuid' => $saved['record']['uuid'] );
                }
            } catch ( SourceError $e ) { if ( in_array( $e->status, array( 429, 502 ), true ) ) { throw $e; } $job['results'][] = array( 'title' => $item['title'], 'status' => 'Needs review: ' . $e->getMessage(), 'uuid' => '' ); }
            catch ( \Throwable $e ) { $job['results'][] = array( 'title' => $item['title'], 'status' => 'Needs review: ' . ( $e instanceof \InvalidArgumentException ? $e->getMessage() : 'The episode could not be saved. Check permissions or concurrent edits and retry individually.' ), 'uuid' => '' ); }
            ++$job['index']; set_site_transient( 'msc_open_job_' . $token, $job, DAY_IN_SECONDS ); return $job;
        } );
    }
}
