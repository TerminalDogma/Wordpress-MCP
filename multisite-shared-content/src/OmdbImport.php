<?php
namespace MSC;

/** Preview snapshots, selective imports, shared Person links and IMDb deduplication. */
final class OmdbImport {
    public const TYPES = array( 'movie' => 'msc_movie', 'series' => 'msc_show', 'episode' => 'msc_episode' );
    public static function matches( string $imdb ): array {
        global $wpdb; OmdbClient::imdb( $imdb );
        $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . " WHERE type IN ('msc_movie','msc_show','msc_episode') AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.external_ids.imdb'))=%s ORDER BY id LIMIT 3", $imdb ) );
        return array_map( static fn( $id ) => Plugin::store()->get( (int) $id ), $ids );
    }
    public static function allowed(): bool {
        if ( ! current_user_can( 'msc_create_global' ) && ! current_user_can( 'msc_edit_global' ) ) { return false; }
        foreach ( self::TYPES as $type ) { if ( isset( Schema::all()[ $type ] ) && Schema::enabled( $type ) ) { return true; } }
        return false;
    }
    private static function text( array $data, string $key ): string {
        $value = trim( (string) ( $data[ $key ] ?? '' ) ); return in_array( strtoupper( $value ), array( '', 'N/A' ), true ) ? '' : $value;
    }
    private static function date( string $value ): string {
        $date = \DateTimeImmutable::createFromFormat( '!d M Y', $value, new \DateTimeZone( 'UTC' ) );
        return $date && $date->format( 'd M Y' ) === $value ? $date->format( 'Y-m-d' ) : '';
    }
    private static function names( string $value ): array {
        return array_values( array_unique( array_filter( array_map( static fn( $name ) => trim( preg_replace( '/\s*\([^)]*\)/u', '', $name ) ), explode( ',', $value ) ), static fn( $name ) => '' !== $name && strlen( $name ) <= 180 ) ) );
    }
    public static function people(): array {
        $people = array();
        if ( ! isset( Schema::all()['msc_person'] ) ) { return $people; }
        foreach ( Plugin::store()->query( 'msc_person', array(), 0, 500 ) as $id ) { $r = Plugin::store()->get( $id ); $people[ $r['uuid'] ] = $r['data']['title'] ?? '(Untitled)'; }
        natcasesort( $people ); return $people;
    }
    public static function shows(): array {
        $shows = array(); if ( ! isset( Schema::all()['msc_show'] ) ) { return $shows; }
        foreach ( Plugin::store()->query( 'msc_show', array(), 0, 500 ) as $id ) { $r = Plugin::store()->get( $id ); $shows[ $r['uuid'] ] = $r['data']['title'] ?? '(Untitled)'; }
        natcasesort( $shows ); return $shows;
    }
    private static function person_data( array $credit, array $roles, string $imdb ): array {
        $schema = Schema::get( 'msc_person' ); $data = array( 'title' => $credit['name'] );
        $professions = array_values( array_intersect_key( array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Writer' ), array_flip( array_intersect( $roles, $credit['roles'] ) ) ) );
        if ( 'multiselect' === ( $schema['fields']['professions']['type'] ?? '' ) ) { $data['professions'] = $professions; }
        if ( 'json' === ( $schema['fields']['additional_metadata']['type'] ?? '' ) ) { $data['additional_metadata'] = array( 'omdb_credit_source' => $imdb ); }
        return Schema::values( 'msc_person', $data );
    }
    private static function map( array $data, string $type ): array {
        $values = array( 'title' => self::text( $data, 'Title' ) ); $plot = self::text( $data, 'Plot' );
        if ( $plot ) { $values['content'] = '<p>' . esc_html( $plot ) . '</p>'; $values['excerpt'] = wp_trim_words( $plot, 55 ); }
        foreach ( array( 'Genre' => 'genres', 'Country' => 'country', 'Language' => 'language' ) as $source => $field ) {
            $text = self::text( $data, $source ); if ( $text ) { $list = array_map( 'trim', explode( ',', $text ) ); $values[ $field ] = 'genres' === $field ? $list : $list[0]; }
        }
        $released = self::date( self::text( $data, 'Released' ) );
        if ( $released ) { $values[ 'msc_show' === $type ? 'premiere_date' : 'release_date' ] = $released; }
        if ( preg_match( '/^([0-9]+) min$/D', self::text( $data, 'Runtime' ), $runtime ) ) { $values['runtime'] = (int) $runtime[1]; }
        if ( 'msc_movie' === $type ) {
            foreach ( array( 'Director' => 'director', 'Writer' => 'writers', 'Rated' => 'content_rating', 'Production' => 'production_companies' ) as $source => $field ) { $text = self::text( $data, $source ); if ( $text ) { $values[ $field ] = $text; } }
            $actors = self::text( $data, 'Actors' ); if ( $actors ) { $values['cast'] = '<p>' . esc_html( $actors ) . '</p>'; }
            $disc = self::date( self::text( $data, 'DVD' ) ); if ( $disc ) { $values['physical_release_date'] = $disc; }
        }
        if ( 'msc_show' === $type && ctype_digit( self::text( $data, 'totalSeasons' ) ) ) { $values['number_of_seasons'] = (int) $data['totalSeasons']; }
        if ( 'msc_episode' === $type ) {
            foreach ( array( 'Season' => 'season_number', 'Episode' => 'episode_number' ) as $source => $field ) { if ( ctype_digit( self::text( $data, $source ) ) ) { $values[ $field ] = (int) $data[ $source ]; } }
        }
        $website = self::text( $data, 'Website' ); if ( $website && wp_http_validate_url( $website ) ) { $values['official_website'] = $website; }
        $values['additional_metadata'] = array( 'omdb' => array_merge( $data, array( 'source' => 'https://www.omdbapi.com/', 'fetched_at' => gmdate( 'c' ) ) ) );
        return $values;
    }
    public static function preview( string $imdb, string $target = '' ): array {
        if ( ! self::allowed() ) { throw new OmdbError( 'Global editing permission and an enrolled title type are required.', 403 ); }
        $data = OmdbClient::details( $imdb ); $type = self::TYPES[ $data['Type'] ];
        $schema = Schema::get( $type );
        if ( ! Schema::enabled( $type ) ) { throw new OmdbError( 'Enable this post type on the current site before importing.', 403 ); }
        if ( 'json' !== ( $schema['fields']['external_ids']['type'] ?? '' ) ) { throw new OmdbError( 'This type needs an external_ids JSON field to retain the IMDb identity.' ); }
        $matches = self::matches( $imdb );
        if ( count( $matches ) > 1 ) { throw new OmdbError( 'Multiple shared records already use this IMDb ID. Resolve them before importing.', 409 ); }
        $r = $target ? Plugin::store()->get( $target ) : ( $matches[0] ?? null );
        if ( $target && ! $r ) { throw new OmdbError( 'The destination record was not found.', 404 ); }
        if ( $r && ( $r['type'] !== $type || 'active' !== $r['state'] ) ) { throw new OmdbError( 'The existing record has another type or is archived/trashed. Review it before importing.', 409 ); }
        if ( $r && ! empty( $r['data']['external_ids']['imdb'] ) && $r['data']['external_ids']['imdb'] !== $imdb || $r && $matches && $matches[0]['uuid'] !== $r['uuid'] ) { throw new OmdbError( 'This IMDb ID belongs to a different shared record.', 409 ); }
        Permissions::require( $r ? 'msc_edit_global' : 'msc_create_global', $type );
        $fields = array(); $warnings = array(); $canonical = Schema::defaults()[ $type ]['fields'];
        foreach ( self::map( $data, $type ) as $key => $value ) {
            $definition = $schema['fields'][ $key ] ?? null; $destination = $key;
            if ( isset( $canonical[ $key ]['property'] ) ) { foreach ( $schema['fields'] as $candidate => $field ) { if ( ( $field['property'] ?? '' ) === $canonical[ $key ]['property'] ) { $destination = $candidate; $definition = $field; break; } } }
            if ( ! $definition ) { continue; }
            try { $fields[ $destination ] = Schema::sanitize( $definition, $value ); }
            catch ( \Throwable $e ) { $warnings[] = 'Skipped a value that does not fit your field configuration: ' . ( $definition['label'] ?? $key ); }
        }
        $credits = array(); $people = self::people();
        if ( Schema::enabled( 'msc_person' ) ) {
            foreach ( array( 'Actors' => 'actors', 'Director' => 'directors', 'Writer' => 'screenwriters' ) as $source => $role ) {
                if ( 'relationship_list' !== ( $schema['fields'][ $role ]['type'] ?? '' ) || 'msc_person' !== ( $schema['fields'][ $role ]['target'] ?? '' ) ) { continue; }
                $names = array_slice( self::names( self::text( $data, $source ) ), 0, 30 ); if ( ! $names ) { continue; }
                $fields[ $role ] = $names;
                foreach ( $names as $name ) { $hash = substr( hash( 'sha256', PersonIdentity::name( $name ) ), 0, 24 ); $credits[ $hash ] = array( 'name' => $credits[ $hash ]['name'] ?? $name, 'roles' => array_unique( array_merge( $credits[ $hash ]['roles'] ?? array(), array( $role ) ) ) ); }
            }
        }
        $parent = '';
        if ( 'msc_episode' === $type && ! empty( $data['seriesID'] ) && preg_match( '/^tt[0-9]{7,12}$/D', $data['seriesID'] ) ) { $parents = self::matches( $data['seriesID'] ); if ( 1 === count( $parents ) && 'msc_show' === $parents[0]['type'] && 'active' === $parents[0]['state'] ) { $parent = $parents[0]['uuid']; } }
        if ( 'msc_episode' === $type && 'relationship' === ( $schema['fields']['parent_show']['type'] ?? '' ) ) { $fields['parent_show'] = $parent; }
        $poster_field = 'msc_episode' === $type ? 'episode_image' : 'poster';
        $person_matches = PersonIdentity::review( $credits ); foreach ( $credits as $key => &$credit ) { $credit['suggested'] = $person_matches[ $key ]['suggested']; } unset( $credit );
        $snapshot = array( 'user' => get_current_user_id(), 'site' => get_current_blog_id(), 'network' => get_current_network_id(), 'type' => $type, 'imdb' => $imdb, 'data' => $data, 'fields' => $fields, 'credits' => $credits, 'person_matches' => $person_matches, 'target' => $r['uuid'] ?? '', 'version' => (int) ( $r['version'] ?? 0 ), 'warnings' => $warnings, 'poster_field' => 'media' === ( $schema['fields'][ $poster_field ]['type'] ?? '' ) ? $poster_field : '' );
        $token = wp_generate_uuid4(); set_site_transient( 'msc_omdb_preview_' . $token, $snapshot, 15 * MINUTE_IN_SECONDS );
        $snapshot['token'] = $token; return $snapshot;
    }
    private static function snapshot( string $token ): array {
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $token ) ) { throw new OmdbError( 'Open a fresh OMDb preview before importing.' ); }
        $s = OmdbClient::fresh_transient( 'msc_omdb_preview_' . $token );
        if ( ! is_array( $s ) || $s['user'] !== get_current_user_id() || $s['site'] !== get_current_blog_id() || $s['network'] !== get_current_network_id() ) { throw new OmdbError( 'This preview expired or belongs to another user/site. Search again.', 403 ); }
        return $s;
    }
    private static function poster( string $imdb, string $url ): array {
        if ( ! current_user_can( 'upload_files' ) ) { throw new OmdbError( 'Media upload permission is required to import a poster.', 403 ); }
        $url = OmdbClient::poster_url( $url ); if ( ! $url ) { throw new OmdbError( 'The poster URL is unavailable or unsupported.' ); }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $file = wp_tempnam( 'omdb-poster' ); if ( ! $file ) { throw new OmdbError( 'Could not prepare a local poster file.' ); }
        try {
            $response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'stream' => true, 'filename' => $file, 'limit_response_size' => 5 * MB_IN_BYTES, 'sslverify' => true ) );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $file ) || filesize( $file ) >= 5 * MB_IN_BYTES ) { throw new OmdbError( 'The poster could not be downloaded.' ); }
            $mime = wp_get_image_mime( $file ); $extension = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $mime ] ?? '';
            $dimensions = @getimagesize( $file );
            if ( ! $extension || ! $dimensions || $dimensions[0] > 10000 || $dimensions[1] > 10000 || $dimensions[0] * $dimensions[1] > 20000000 ) { throw new OmdbError( 'The poster did not contain a supported image size/format.' ); }
            $id = media_handle_sideload( array( 'name' => $imdb . '-poster.' . $extension, 'tmp_name' => $file ), 0, 'OMDb poster — ' . $imdb );
            if ( is_wp_error( $id ) ) { throw new OmdbError( 'WordPress could not save the poster.' ); }
            return array( 'site_id' => get_current_blog_id(), 'attachment_id' => (int) $id );
        } finally { if ( is_file( $file ) ) { wp_delete_file( $file ); } }
    }
    public static function import( array $request ): array {
        if ( ! is_string( $request['nonce'] ?? null ) || ! wp_verify_nonce( $request['nonce'], 'msc_omdb_import' ) ) { throw new OmdbError( 'The import security token expired. Open a fresh preview.', 403 ); }
        if ( ! is_string( $request['token'] ?? null ) ) { throw new OmdbError( 'Open a fresh OMDb preview before importing.' ); }
        $s = self::snapshot( $request['token'] );
        Permissions::require( $s['target'] ? 'msc_edit_global' : 'msc_create_global', $s['type'] );
        $selected = $request['fields'] ?? array(); $choices = $request['people'] ?? array();
        if ( ! is_array( $selected ) || ! array_is_list( $selected ) || array_filter( $selected, static fn( $value ) => ! is_string( $value ) ) || array_diff( $selected, array_keys( $s['fields'] ) ) || ! is_array( $choices ) || array_diff_key( $choices, $s['credits'] ) || array_filter( $choices, static fn( $value ) => ! is_string( $value ) ) || isset( $request['parent_show'] ) && ! is_string( $request['parent_show'] ) ) { throw new OmdbError( 'The submitted fields do not match this preview.' ); }
        $selected = array_values( array_unique( $selected ) );
        return Database::lock( 'omdb:title:' . $s['imdb'], static function () use ( $s, $selected, $choices, $request ) {
            $matches = self::matches( $s['imdb'] ); $r = $s['target'] ? Plugin::store()->get( $s['target'] ) : null;
            if ( count( $matches ) > 1 || $r && $matches && $matches[0]['uuid'] !== $r['uuid'] ) { throw new OmdbError( 'The IMDb identity changed. Open a fresh preview.', 409 ); }
            if ( ! $s['target'] && $matches ) { if ( 'active' !== $matches[0]['state'] || $matches[0]['type'] !== $s['type'] ) { throw new OmdbError( 'An archived, trashed or differently typed record already has this IMDb ID.', 409 ); } return array( 'record' => $matches[0], 'people' => array(), 'warnings' => array( 'This title was already imported. The existing record was retained.' ) ); }
            if ( $s['target'] && ( ! $r || 'active' !== $r['state'] || $r['type'] !== $s['type'] || (int) $r['version'] !== $s['version'] ) ) { throw new OmdbError( 'Conflict: the shared record changed. Open a fresh preview.', 409 ); }
            $values = array_intersect_key( $s['fields'], array_flip( $selected ) );
            $roles = array_intersect( array( 'actors', 'directors', 'screenwriters' ), $selected );
            foreach ( $roles as $role ) { $values[ $role ] = array(); }
            if ( 'msc_episode' === $s['type'] && in_array( 'parent_show', $selected, true ) ) { $values['parent_show'] = (string) ( $request['parent_show'] ?? '' ); }
            if ( 'msc_episode' === $s['type'] ) {
                $parent = array_key_exists( 'parent_show', $values ) ? $values['parent_show'] : ( $r['data']['parent_show'] ?? '' );
                if ( ! $parent ) { throw new OmdbError( 'Select and enable the parent TV Show field. Import that Show first if it is not in this catalog.' ); }
                Schema::sanitize( Schema::get( $s['type'] )['fields']['parent_show'], $parent );
                $show = Plugin::store()->get( $parent ); $identity = $show['data']['external_ids']['imdb'] ?? '';
                if ( $identity && ! empty( $s['data']['seriesID'] ) && $identity !== $s['data']['seriesID'] ) { throw new OmdbError( 'The selected TV Show has a different IMDb ID from the Episode’s series.' ); }
            }
            $external = $r['data']['external_ids'] ?? array(); if ( ! is_array( $external ) || ! empty( $external['imdb'] ) && $external['imdb'] !== $s['imdb'] ) { throw new OmdbError( 'The destination already identifies another IMDb title.', 409 ); }
            $values['external_ids'] = array_replace( $external, array( 'imdb' => $s['imdb'] ) );
            if ( ! $r ) { $has_title = false; foreach ( Schema::get( $s['type'] )['fields'] as $key => $definition ) { if ( 'post_title' === ( $definition['property'] ?? '' ) && isset( $values[ $key ] ) && '' !== (string) $values[ $key ] ) { $has_title = true; } } if ( ! $has_title ) { throw new OmdbError( 'Select the title field when creating a new record.' ); } }
            if ( isset( $values['additional_metadata'] ) ) { $old = (array) ( $r['data']['additional_metadata'] ?? array() ); $values['additional_metadata'] = array_replace( $old, array( 'omdb' => array_replace( (array) ( $old['omdb'] ?? array() ), $values['additional_metadata']['omdb'] ) ) ); }
            Schema::values( $s['type'], $values ); // Validate all ordinary values before creating people or files.
            $used = array_filter( $s['credits'], static fn( $credit ) => (bool) array_intersect( $roles, $credit['roles'] ) );
            foreach ( $used as $key => $credit ) {
                $choice = $choices[ $key ] ?? '';
                if ( in_array( $choice, array( 'new', 'new_distinct' ), true ) ) { Permissions::require( 'msc_create_global', 'msc_person' ); self::person_data( $credit, $roles, $s['imdb'] ); }
                elseif ( $choice ) { $person = Plugin::store()->get( $choice ); if ( ! $person || 'msc_person' !== $person['type'] || 'active' !== $person['state'] ) { throw new OmdbError( 'A selected Person is unavailable. Open a fresh preview.' ); } }
            }
            $warnings = $s['warnings']; $media = null;
            if ( ! empty( $request['poster'] ) ) {
                if ( ! $s['poster_field'] || ! $s['data']['Poster'] ) { throw new OmdbError( 'This preview has no importable poster.' ); }
                try { $media = self::poster( $s['imdb'], $s['data']['Poster'] ); $values[ $s['poster_field'] ] = $media; }
                catch ( OmdbError $e ) { if ( 403 === $e->status ) { throw $e; } $warnings[] = 'The poster was unavailable. Other selected fields were saved; the current image was retained. Retry from a fresh preview.'; }
            }
            $created = $touched = array();
            try {
                $saved = PersonIdentity::locked( static function () use ( $s, $values, $used, $choices, $roles, $r, &$created, &$touched ) { return Database::transaction( static function () use ( $s, $values, $used, $choices, $roles, $r, &$created, &$touched ) {
                    $linked = array();
                    foreach ( $used as $key => $credit ) {
                        $choice = $choices[ $key ] ?? ''; if ( ! $choice ) { continue; }
                        $person = PersonIdentity::resolve( $credit['name'], array(), '', $choice );
                        if ( ! $person ) { $person = PersonIdentity::remember( Plugin::store()->create( 'msc_person', self::person_data( $credit, $roles, $s['imdb'] ) ) ); $created[] = $person['uuid']; }
                        elseif ( current_user_can( 'msc_edit_global' ) && 'multiselect' === ( Schema::get( 'msc_person' )['fields']['professions']['type'] ?? '' ) ) { $professions = PersonIdentity::professions( $person, array_intersect( $roles, $credit['roles'] ) ); if ( $professions !== ( $person['data']['professions'] ?? array() ) ) { $person = PersonIdentity::remember( Plugin::store()->update( $person['uuid'], array( 'professions' => $professions ), (int) $person['version'] ) ); } }
                        $choice = $person['uuid'];
                        $touched[] = $choice;
                        $linked[ $key ] = $choice;
                    }
                    foreach ( $roles as $role ) { foreach ( $s['fields'][ $role ] as $name ) { $key = substr( hash( 'sha256', PersonIdentity::name( $name ) ), 0, 24 ); if ( isset( $linked[ $key ] ) ) { $values[ $role ][] = $linked[ $key ]; } } $values[ $role ] = array_values( array_unique( $values[ $role ] ) ); }
                    if ( ! $r ) { return Plugin::store()->create( $s['type'], $values ); }
                    $changes = array_filter( $values, static fn( $value, $key ) => ! array_key_exists( $key, $r['data'] ) || $r['data'][ $key ] !== $value, ARRAY_FILTER_USE_BOTH );
                    return $changes ? Plugin::store()->update( $r['uuid'], $changes, $s['version'] ) : $r;
                } ); } );
            } catch ( \Throwable $e ) { if ( $media ) { wp_delete_attachment( $media['attachment_id'], true ); } if ( $e instanceof SourceError ) { throw new OmdbError( $e->getMessage(), $e->status ); } throw $e; }
            foreach ( array_unique( array_merge( $touched, array( $saved['uuid'] ) ) ) as $uuid ) { try { Propagation::send( Plugin::store()->get( $uuid ) ); } catch ( \Throwable $e ) { $warnings[] = 'The shared record is saved; delivery remains queued on one or more sites.'; } }
            return array( 'record' => $saved, 'people' => $created, 'warnings' => array_values( array_unique( $warnings ) ) );
        } );
    }
}
