<?php
namespace MSC;

/** Editorial story chronology with portable title and artwork references. */
final class Timelines {
    public const TYPE = 'msc_timeline';
    public const DATES = array( 'exact' => 'Exact', 'approximate' => 'Approximate', 'relative' => 'Relative', 'undated' => 'Undated' );

    public static function schemas( array $common ): array {
        return array( self::TYPE => array(
            'label' => 'Timelines', 'slug' => 'timelines', 'archive' => true,
            'default_status' => 'draft', 'noindex' => true,
            'fields' => $common + array(
                'franchise' => array( 'type' => 'relationship', 'target' => 'msc_franchise', 'label' => 'Related franchise' ),
                'chronology' => array( 'type' => 'json', 'format' => 'chronology', 'label' => 'Timeline builder' ),
            ),
        ) );
    }

    public static function enroll(): void {
        if ( ! isset( Schema::all()[ self::TYPE ] ) ) { return; }
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'spam' => 0, 'archived' => 0 ) ) as $site ) {
            $id = (int) $site->blog_id;
            if ( get_blog_option( $id, 'msc_timeline_profile_version' ) || ! ( Schema::enabled( 'msc_movie', $id ) || Schema::enabled( 'msc_show', $id ) ) ) { continue; }
            if ( ! Schema::enabled( self::TYPE, $id ) ) {
                Schema::participate( $id, array_merge( get_blog_option( $id, 'msc_types', array() ), array( self::TYPE ) ) );
            }
            update_blog_option( $id, 'msc_timeline_profile_version', 1 );
        }
    }

    private static function text( $value, int $max, string $name ): string {
        if ( ! is_string( $value ) ) { throw new \InvalidArgumentException( 'Invalid ' . $name . '.' ); }
        $text = sanitize_text_field( $value );
        if ( mb_strlen( $text ) > $max ) { throw new \InvalidArgumentException( $name . ' must be ' . $max . ' characters or fewer.' ); }
        return $text;
    }

    public const LEGACY_TYPES = array( 'movie' => 'msc_movie', 'show' => 'msc_show' );

    /** Adapt records saved before named columns and sections were introduced. */
    public static function model( array $model ): array {
        if ( isset( $model['columns'] ) ) { return $model; }
        $model['columns'] = array( array( 'id' => 'movie', 'label' => 'Movies' ), array( 'id' => 'show', 'label' => 'TV Shows' ) );
        $sections = array();
        if ( is_array( $model['entries'] ?? null ) ) {
            foreach ( $model['entries'] as &$entry ) {
                if ( ! is_array( $entry ) ) { continue; }
                $entry['column'] = $entry['kind'] ?? '';
                $label = $entry['section'] ?? '';
                if ( is_string( $label ) && '' !== $label ) {
                    $id = 'section-' . substr( hash( 'sha256', $label ), 0, 20 );
                    $sections[ $id ] = array( 'id' => $id, 'label' => $label );
                    $entry['section'] = $id;
                }
            }
            unset( $entry );
        }
        $model['sections'] = array_values( $sections );
        return $model;
    }

    private static function choices( $rows, int $limit, string $name, bool $colors = false ): array {
        if ( ! is_array( $rows ) || ! array_is_list( $rows ) || count( $rows ) > $limit ) {
            throw new \InvalidArgumentException( 'Too many or invalid timeline ' . $name . ' choices.' );
        }
        $choices = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) { throw new \InvalidArgumentException( 'Invalid timeline ' . $name . '.' ); }
            $id = self::text( $row['id'] ?? '', 48, $name . ' ID' );
            $label = self::text( $row['label'] ?? '', 100, $name . ' name' );
            if ( ! preg_match( '/^[a-z][a-z0-9-]*$/D', $id ) || isset( $choices[ $id ] ) || '' === $label ) {
                throw new \InvalidArgumentException( 'Each timeline ' . $name . ' needs a unique ID and a name.' );
            }
            $choices[ $id ] = array( 'id' => $id, 'label' => $label );
            if ( $colors ) {
                $color = self::text( $row['color'] ?? '', 7, 'era color' );
                if ( ! preg_match( '/^#[0-9a-f]{6}$/iD', $color ) ) { throw new \InvalidArgumentException( 'Use a six-digit hex color for each era.' ); }
                $choices[ $id ]['color'] = strtolower( $color );
            }
        }
        return $choices;
    }

    public static function sanitize( $value ): array {
        if ( '' === $value || null === $value ) { $value = array(); }
        if ( ! is_array( $value ) ) { throw new \InvalidArgumentException( 'Invalid timeline data.' ); }
        $value = self::model( $value );
        $columns = self::choices( $value['columns'], 6, 'column' );
        if ( ! $columns ) { throw new \InvalidArgumentException( 'Add at least one timeline column.' ); }
        $sections = self::choices( $value['sections'] ?? array(), 300, 'section' );
        $colors = self::choices( $value['eras'] ?? array(), 24, 'era', true );
        $entries = $value['entries'] ?? array();
        if ( ! is_array( $entries ) || ! array_is_list( $entries ) || count( $entries ) > 300 ) {
            throw new \InvalidArgumentException( 'A timeline supports up to 300 entries.' );
        }
        $link_types = self::link_types();
        $rows = array();
        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) ) { throw new \InvalidArgumentException( 'Invalid timeline entry.' ); }
            $row = array();
            foreach ( array( 'title' => 200, 'date' => 140, 'column' => 48, 'precision' => 16, 'era' => 48, 'section' => 48, 'note' => 300, 'item' => 36 ) as $field => $max ) {
                $row[ $field ] = self::text( $entry[ $field ] ?? '', $max, $field );
            }
            if ( '' === $row['title'] || ! isset( $columns[ $row['column'] ], self::DATES[ $row['precision'] ] ) || ( $row['era'] && ! isset( $colors[ $row['era'] ] ) ) ) {
                throw new \InvalidArgumentException( 'Each entry needs a title, a column, a date type and an available era if one is selected.' );
            }
            if ( $row['section'] && ! isset( $sections[ $row['section'] ] ) ) { throw new \InvalidArgumentException( 'Choose a section defined for this timeline.' ); }
            if ( 'undated' !== $row['precision'] && '' === $row['date'] ) {
                throw new \InvalidArgumentException( 'Enter a story date for ' . $row['title'] . ', or choose Undated.' );
            }
            if ( $row['item'] ) {
                $target = wp_is_uuid( $row['item'] ) ? Plugin::store()->get( $row['item'] ) : null;
                if ( ! $target || ! isset( $link_types[ $target['type'] ] ) ) {
                    throw new \InvalidArgumentException( 'Choose an available shared catalog entry.' );
                }
            }
            $row['image'] = Media::sanitize( $entry['image'] ?? array() );
            $rows[] = $row;
        }
        return array( 'columns' => array_values( $columns ), 'sections' => array_values( $sections ), 'eras' => array_values( $colors ), 'entries' => $rows );
    }

    /** Use effective values on this site and verify the UUID after inspecting native meta. */
    public static function related( array $item ): array {
        if ( ! Schema::enabled( self::TYPE ) || self::TYPE === $item['type'] ) { return array(); }
        $uuid = $item['uuid'];
        $posts = get_posts( array(
            'post_type' => self::TYPE, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => -1,
            'meta_query' => array( 'relation' => 'OR',
                array( 'key' => 'msc_franchise', 'value' => $uuid ),
                array( 'key' => 'msc_chronology', 'value' => '"' . $uuid . '"', 'compare' => 'LIKE' ),
            ),
        ) );
        $records = array();
        foreach ( $posts as $post ) {
            $global = Plugin::store()->by_post( $post->ID );
            $record = $global ? Presentation::record( $global['id'] ) : null;
            if ( ! $record ) { continue; }
            $indices = array();
            foreach ( $record['data']['chronology']['entries'] ?? array() as $index => $entry ) {
                if ( ( $entry['item'] ?? '' ) === $uuid ) { $indices[] = $index; }
            }
            if ( ! $indices && ( $record['data']['franchise'] ?? '' ) !== $uuid ) { continue; }
            $record['timeline_matches'] = $indices;
            $records[] = $record;
        }
        return $records;
    }

    private static function link_types(): array {
        return array_diff_key( Schema::all(), array( self::TYPE => true ) );
    }

    private static function title_choice( array $record ): array {
        $uuid = $record['uuid'];
        $local = $record['mapping'] ?? Plugin::store()->mapping( (int) $record['id'] );
        $post = (int) ( $local['post_id'] ?? 0 );
        $public = Presentation::record( $uuid );
        if ( ! $public && ( ! $post || ! current_user_can( 'edit_post', $post ) ) ) { return array(); }
        $data = $public ?? Plugin::store()->resolved( $uuid ) ?? $record;
        $art = Presentation::artwork( $data, 'medium' );
        return array(
            'id' => $uuid, 'title' => Presentation::text( $data['data']['title'] ?? '' ),
            'type' => Schema::get( $data['type'] )['label'], 'image' => $art['url'] ?? '', 'id_label' => (string) $post,
        );
    }

    public static function control( string $name, string $id, array $value ): void {
        $value = self::model( $value );
        $previews = array();
        $titles = array();
        foreach ( $value['entries'] ?? array() as $entry ) {
            $ref = $entry['image'] ?? array();
            if ( $ref ) { $previews[ $ref['site_id'] . ':' . $ref['attachment_id'] ] = Media::url( $ref, 'medium' ); }
            if ( empty( $entry['item'] ) || isset( $titles[ $entry['item'] ] ) ) { continue; }
            $record = Plugin::store()->get( $entry['item'] );
            $titles[ $entry['item'] ] = $record ? self::title_choice( $record ) : array();
        }
        wp_enqueue_script( 'msc-timeline-editor', plugins_url( 'assets/timeline-editor.js', MSC_FILE ), array( 'media-editor' ), MSC_VERSION, true );
        $config = array( 'previews' => $previews, 'titles' => $titles, 'dates' => self::DATES, 'types' => array_map( static fn( $schema ) => $schema['label'], array_filter( self::link_types(), array( Schema::class, 'enabled' ), ARRAY_FILTER_USE_KEY ) ), 'site' => get_current_blog_id(), 'search' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'msc_timeline_titles' ) );
        echo '<div class="msc-timeline-editor" data-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
        echo '<textarea class="large-text code" rows="10" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( wp_json_encode( $value, JSON_PRETTY_PRINT ) ) . '</textarea><div class="msc-timeline-controls" hidden></div></div>';
    }

    public static function search(): void {
        check_ajax_referer( 'msc_timeline_titles', 'nonce' );
        if ( ! Schema::enabled( self::TYPE ) || ! ( current_user_can( 'msc_create_global' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_override_local' ) ) ) {
            wp_send_json_error( 'Timeline editing permission is required.', 403 );
        }
        $type = is_string( $_GET['type'] ?? null ) ? $_GET['type'] : '';
        $types = array_keys( array_filter( self::link_types(), array( Schema::class, 'enabled' ), ARRAY_FILTER_USE_KEY ) );
        $types = $type ? array_values( array_intersect( $types, array( $type ) ) ) : $types;
        $query = is_string( $_GET['q'] ?? null ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 0, 150 ) : '';
        $results = array();
        if ( $types && mb_strlen( $query ) >= 2 ) {
            $posts = get_posts( array( 'post_type' => $types, 's' => $query, 'search_columns' => array( 'post_title' ), 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'posts_per_page' => 25 ) );
            foreach ( $posts as $post ) {
                $record = Plugin::store()->by_post( $post->ID );
                if ( ! $record || 'active' !== $record['state'] ) { continue; }
                $choice = self::title_choice( $record );
                if ( $choice ) { $results[] = $choice; }
            }
        }
        wp_send_json_success( $results );
    }
}
