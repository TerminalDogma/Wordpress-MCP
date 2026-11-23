<?php
/** Complete the Doomsday example using site-local overrides and publication. */
use MSC\{CastRoles, PersonIdentity, Plugin, Presentation, Schema};

return static function ( array $movie, int $site ): void {
    $store = Plugin::store();
    $film = $store->resolved( $movie['id'] );
    $sample = json_decode( file_get_contents( __DIR__ . '/doomsday-credits.json' ), true, 32, JSON_THROW_ON_ERROR );
    $groups = array( 'actors' => array_keys( $sample['cast'] ) ) + $sample['crew'];
    $professions = array( 'actors' => 'Actor', 'directors' => 'Director', 'screenwriters' => 'Writer', 'producers' => 'Producer',
        'cinematographers' => 'Cinematographer', 'film_editors' => 'Editor', 'composers' => 'Composer' );
    $keys = array();
    foreach ( $sample['people'] as $name => $info ) {
        foreach ( array( $name, ...( $info['aliases'] ?? array() ) ) as $alias ) { $keys[ PersonIdentity::name( $alias ) ] = $name; }
    }
    $linked = $names = $jobs = $updated = array();
    foreach ( $groups as $field => $list ) {
        $linked[ $field ] = $film['data'][ $field ] ?? array();
        foreach ( $linked[ $field ] as $uuid ) {
            $person = $store->get( $uuid );
            if ( ! $person || 'active' !== $person['state'] || 'msc_person' !== $person['type'] ) { continue; }
            $name = $person['data']['title'];
            $canonical = $keys[ PersonIdentity::name( $name ) ] ?? $name;
            $names[ $canonical ] ??= $uuid;
            $jobs[ $uuid ][] = $professions[ $field ];
        }
    }
    $checked = $published = $photos = 0;
    $missing = array();
    if ( Schema::enabled( 'msc_person' ) ) {
        PersonIdentity::locked( static function () use ( $store, $sample, $groups, $professions, &$names, &$linked, &$jobs, &$missing ): void {
            foreach ( $groups as $field => $list ) {
                foreach ( $list as $name ) {
                    if ( ! isset( $names[ $name ] ) ) {
                        $info = $sample['people'][ $name ];
                        $ids = empty( $info['wikidata'] ) ? array() : array( 'wikidata' => $info['wikidata'] );
                        try {
                            $match = PersonIdentity::assess( $name, $ids );
                            $candidates = $match['strong'] ?: $match['possible'];
                            foreach ( $info['aliases'] ?? array() as $alias ) {
                                if ( $match['strong'] ) { break; }
                                $candidates += PersonIdentity::assess( $alias, $ids )['possible'];
                            }
                            if ( count( $candidates ) > 1 ) { throw new RuntimeException( 'Multiple People match this name.' ); }
                            $person = reset( $candidates ) ?: PersonIdentity::remember( $store->create( 'msc_person', array_filter( array( 'title' => $name, 'external_ids' => $ids ) ) ) );
                            if ( 'active' !== $person['state'] ) { throw new RuntimeException( 'The existing Person is unavailable.' ); }
                            $names[ $name ] = $person['uuid'];
                        } catch ( Throwable $error ) {
                            $missing[] = $name;
                            WP_CLI::warning( $name . ': ' . $error->getMessage() );
                            continue;
                        }
                    }
                    $uuid = $names[ $name ];
                    $linked[ $field ][] = $uuid;
                    $jobs[ $uuid ][] = $professions[ $field ];
                }
            }
        } );
        foreach ( $jobs as $uuid => $roles ) {
            $person = $store->resolved( $uuid );
            if ( empty( $person['mapping']['post_id'] ) && empty( $person['mapping']['suppressed'] ) ) {
                Plugin::materializer()->sync( (int) $person['id'], $site );
                $person = $store->resolved( $uuid );
            }
            $mapping = $person['mapping'];
            $name = $person['data']['title'];
            $canonical = $keys[ PersonIdentity::name( $name ) ] ?? $name;
            if ( empty( $mapping['post_id'] ) || ! in_array( $mapping['status'], array( 'draft', 'publish' ), true ) || ! empty( $mapping['suppressed'] ) ) {
                $missing[] = $name;
                continue;
            }
            ++$checked;
            $values = array();
            $existing_roles = $person['data']['professions'] ?? array();
            $new_roles = array_values( array_unique( array_merge( $existing_roles, $roles ) ) );
            if ( $existing_roles !== $new_roles ) { $values['professions'] = $new_roles; }
            if ( empty( Presentation::artwork( $person )['url'] ) ) {
                $portrait = $sample['people'][ $canonical ]['portrait'] ?? null;
                if ( $portrait ) {
                    $asset = $portrait['file'];
                    $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_sat_doomsday_portrait', 'meta_value' => $asset ) );
                    $id = (int) ( $existing[0] ?? 0 );
                    if ( ! $id || ! is_file( (string) get_attached_file( $id ) ) ) {
                        $bytes = file_get_contents( __DIR__ . '/doomsday-portraits/' . $asset );
                        if ( ! $bytes || 'image/jpeg' !== ( getimagesizefromstring( $bytes )['mime'] ?? '' ) ) { throw new RuntimeException( 'Invalid portrait file for ' . $name ); }
                        $upload = wp_upload_bits( $asset, null, $bytes );
                        if ( $upload['error'] ) { throw new RuntimeException( $upload['error'] ); }
                        $meta = array( '_sat_doomsday_portrait' => $asset, '_wp_attachment_image_alt' => isset( $portrait['source'] ) ? $name : $name . ' — initials artwork' );
                        if ( isset( $portrait['source'] ) ) { $meta['_msc_open_source'] = $portrait['source']; }
                        $id = wp_insert_attachment( wp_slash( array(
                            'post_title' => $name . ( isset( $portrait['source'] ) ? ' portrait' : ' initials artwork' ),
                            'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'meta_input' => $meta,
                        ) ), $upload['file'], 0, true );
                        if ( is_wp_error( $id ) ) {
                            wp_delete_file( $upload['file'] );
                            throw new RuntimeException( $id->get_error_message() );
                        }
                        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
                    }
                    $values['headshot'] = array( 'site_id' => $site, 'attachment_id' => $id );
                    ++$photos;
                } else {
                    $missing[] = $name;
                }
            }
            $status = 'draft' === $mapping['status'] ? 'publish' : null;
            if ( $values || $status ) {
                $store->local( $uuid, $values, array(), (int) $mapping['local_version'], $status );
                if ( $status && 'publish' === get_post_status( (int) $mapping['post_id'] ) ) { ++$published; }
            }
        }
        $roles = CastRoles::by_actor( $film['data']['cast_roles'] ?? '' );
        $lines = array();
        foreach ( $linked['actors'] as $uuid ) {
            $person = $store->resolved( $uuid );
            if ( ! $person || 'msc_person' !== $person['type'] ) { continue; }
            $name = $person['data']['title'];
            $key = PersonIdentity::name( $name );
            $role = $roles[ $key ] ?? $sample['cast'][ $keys[ $key ] ?? $name ] ?? '';
            if ( $role ) { $lines[ $key ] = $name . ' | ' . $role; }
        }
        foreach ( preg_split( '/\R/u', $film['data']['cast_roles'] ?? '', -1, PREG_SPLIT_NO_EMPTY ) as $line ) {
            $key = PersonIdentity::name( trim( explode( '|', $line, 2 )[0] ) );
            $lines[ $key ] ??= $line;
        }
        $updated['cast_roles'] = implode( "\n", $lines );
        foreach ( $linked as $field => $uuids ) { $updated[ $field ] = array_values( array_unique( $uuids ) ); }
    }
    if ( empty( $film['data']['trailers'] ) ) { $updated['trailers'] = implode( "\n", $sample['trailers'] ); }
    if ( empty( $film['data']['release_date'] ) ) { $updated['release_date'] = '2026-12-18'; }
    if ( 'avengers-doomsday-takeover-preview' === get_post_field( 'post_name', $film['mapping']['post_id'] ) && array( 'Action', 'Adventure' ) === ( $film['data']['genres'] ?? array() ) ) {
        $updated['genres'] = array( 'action film', 'adventure film', 'science fiction film' );
    }
    $updated = array_filter( $updated, static fn( $value, $key ) => $value !== ( $film['data'][ $key ] ?? null ), ARRAY_FILTER_USE_BOTH );
    if ( $updated ) { $store->local( $movie['uuid'], $updated, array(), (int) $film['mapping']['local_version'] ); }
    WP_CLI::log( sprintf( '%d linked profiles checked. Added %d profile images and published %d drafts.', $checked, $photos, $published ) );
    if ( $missing ) { WP_CLI::warning( 'Profiles still needing review: ' . implode( ', ', array_unique( $missing ) ) . '.' ); }
};
