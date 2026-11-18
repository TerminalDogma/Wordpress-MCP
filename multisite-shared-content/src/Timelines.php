<?php
namespace MSC;

/** Editorial story chronology with portable title and artwork references. */
final class Timelines {
    public const TYPE = 'msc_timeline';
    public const TRACKS = array( 'movie' => 'msc_movie', 'show' => 'msc_show' );
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

    public static function sanitize( $value ): array {
        if ( '' === $value || null === $value ) { $value = array(); }
        if ( ! is_array( $value ) ) { throw new \InvalidArgumentException( 'Invalid timeline data.' ); }
        $eras = $value['eras'] ?? array();
        $entries = $value['entries'] ?? array();
        if ( ! is_array( $eras ) || ! array_is_list( $eras ) || ! is_array( $entries ) || ! array_is_list( $entries ) || count( $eras ) > 24 || count( $entries ) > 300 ) {
            throw new \InvalidArgumentException( 'A timeline supports up to 24 eras and 300 entries.' );
        }
        $colors = array();
        foreach ( $eras as $era ) {
            if ( ! is_array( $era ) ) { throw new \InvalidArgumentException( 'Invalid era.' ); }
            $id = self::text( $era['id'] ?? '', 48, 'era ID' );
            $label = self::text( $era['label'] ?? '', 80, 'era name' );
            $color = self::text( $era['color'] ?? '', 7, 'era color' );
            if ( ! preg_match( '/^[a-z][a-z0-9-]*$/D', $id ) || isset( $colors[ $id ] ) || ! $label || ! preg_match( '/^#[0-9a-f]{6}$/iD', $color ) ) {
                throw new \InvalidArgumentException( 'Each era needs a unique ID, name and six-digit hex color.' );
            }
            $colors[ $id ] = array( 'id' => $id, 'label' => $label, 'color' => strtolower( $color ) );
        }
        $rows = array();
        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) ) { throw new \InvalidArgumentException( 'Invalid timeline entry.' ); }
            $row = array();
            foreach ( array( 'title' => 200, 'date' => 140, 'kind' => 12, 'precision' => 16, 'era' => 48, 'section' => 100, 'note' => 300, 'item' => 36 ) as $field => $max ) {
                $row[ $field ] = self::text( $entry[ $field ] ?? '', $max, $field );
            }
            if ( '' === $row['title'] || ! isset( self::TRACKS[ $row['kind'] ], self::DATES[ $row['precision'] ] ) || ( $row['era'] && ! isset( $colors[ $row['era'] ] ) ) ) {
                throw new \InvalidArgumentException( 'Each entry needs a title, a track, a date type and an available era if one is selected.' );
            }
            if ( 'undated' !== $row['precision'] && '' === $row['date'] ) {
                throw new \InvalidArgumentException( 'Enter a story date for ' . $row['title'] . ', or choose Undated.' );
            }
            if ( $row['item'] ) {
                $target = wp_is_uuid( $row['item'] ) ? Plugin::store()->get( $row['item'] ) : null;
                if ( ! $target || $target['type'] !== self::TRACKS[ $row['kind'] ] ) {
                    throw new \InvalidArgumentException( 'The linked title must match the entry’s movie or TV track.' );
                }
            }
            $row['image'] = Media::sanitize( $entry['image'] ?? array() );
            $rows[] = $row;
        }
        return array( 'eras' => array_values( $colors ), 'entries' => $rows );
    }

    public static function related( array $franchise ): array {
        if ( ! Schema::enabled( self::TYPE ) ) { return array(); }
        $posts = get_posts( array(
            'post_type' => self::TYPE, 'post_status' => 'publish', 'posts_per_page' => 100,
            'meta_key' => 'msc_franchise', 'meta_value' => $franchise['uuid'],
        ) );
        $records = array();
        foreach ( $posts as $post ) {
            $global = Plugin::store()->by_post( $post->ID );
            $record = $global ? Presentation::record( $global['id'] ) : null;
            if ( $record && ( $record['data']['franchise'] ?? '' ) === $franchise['uuid'] ) { $records[] = $record; }
        }
        return $records;
    }

    public static function control( string $name, string $id, array $value ): void {
        $previews = array();
        $titles = array();
        foreach ( $value['entries'] ?? array() as $entry ) {
            $ref = $entry['image'] ?? array();
            if ( $ref ) { $previews[ $ref['site_id'] . ':' . $ref['attachment_id'] ] = Media::url( $ref, 'thumbnail' ); }
            if ( empty( $entry['item'] ) || isset( $titles[ $entry['item'] ] ) ) { continue; }
            $record = Plugin::store()->get( $entry['item'] );
            $titles[ $entry['item'] ] = $record['data']['title'] ?? 'Unavailable title';
        }
        wp_enqueue_script( 'msc-timeline-editor', plugins_url( 'assets/timeline-editor.js', MSC_FILE ), array( 'media-editor' ), MSC_VERSION, true );
        $config = array( 'previews' => $previews, 'titles' => $titles, 'dates' => self::DATES, 'site' => get_current_blog_id(), 'search' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'msc_timeline_titles' ) );
        echo '<div class="msc-timeline-editor" data-config="' . esc_attr( wp_json_encode( $config ) ) . '">';
        echo '<textarea class="large-text code" rows="10" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( wp_json_encode( $value, JSON_PRETTY_PRINT ) ) . '</textarea><div class="msc-timeline-controls" hidden></div></div>';
    }

    public static function search(): void {
        check_ajax_referer( 'msc_timeline_titles', 'nonce' );
        if ( ! Schema::enabled( self::TYPE ) || ! ( current_user_can( 'msc_create_global' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_override_local' ) ) ) {
            wp_send_json_error( 'Timeline editing permission is required.', 403 );
        }
        $kind = is_string( $_GET['kind'] ?? null ) ? $_GET['kind'] : '';
        $type = self::TRACKS[ $kind ] ?? '';
        $query = is_string( $_GET['q'] ?? null ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 0, 150 ) : '';
        $results = array();
        if ( $type && Schema::enabled( $type ) && mb_strlen( $query ) >= 2 ) {
            $posts = get_posts( array( 'post_type' => $type, 's' => $query, 'search_columns' => array( 'post_title' ), 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'posts_per_page' => 25 ) );
            foreach ( $posts as $post ) {
                $record = Plugin::store()->by_post( $post->ID );
                if ( ! $record || 'active' !== $record['state'] || ( ! Presentation::record( $record['uuid'] ) && ! current_user_can( 'edit_post', $post->ID ) ) ) { continue; }
                $results[] = array( 'id' => $record['uuid'], 'title' => Presentation::text( $post->post_title ), 'id_label' => (string) $post->ID );
            }
        }
        wp_send_json_success( $results );
    }
}
