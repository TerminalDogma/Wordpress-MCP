<?php
namespace MSC;

/** Creates ordinary WordPress posts queued for editorial review whose listings remain live. */
final class ReleasePages {
    public static function allowed(): bool { return current_user_can( 'edit_posts' ) && ( current_user_can( 'msc_override_local' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_create_global' ) ); }
    /** Convert only plugin-generated guides; the rolling calendar hub remains a Page. */
    public static function upgrade(): void {
        if ( ! current_user_can( 'manage_options' ) || get_option( 'msc_release_posts_version', 0 ) >= 1 ) { return; }
        $old = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => '_msc_release_page_key', 'posts_per_page' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) );
        $complete = true;
        foreach ( $old as $page ) {
            if ( ! current_user_can( 'edit_post', $page->ID ) ) { $complete = false; continue; }
            $status = in_array( $page->post_status, array( 'publish', 'future' ), true ) ? 'pending' : $page->post_status;
            $id = wp_update_post( array( 'ID' => $page->ID, 'post_type' => 'post', 'post_parent' => 0, 'post_status' => $status ), true );
            if ( is_wp_error( $id ) ) { throw new \RuntimeException( 'A generated guide could not be moved to Posts for review.' ); }
        }
        if ( $complete && count( $old ) < 100 ) { update_option( 'msc_release_posts_version', 1 ); }
    }
    public static function generate( string $month, int $months, string $type = '', string $service = '' ): array {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Post editing and shared-content permission are required.' ); }
        self::upgrade();
        if ( $months < 1 || $months > 12 ) { throw new \InvalidArgumentException( 'Generate between 1 and 12 monthly pages.' ); }
        $f = UpcomingReleases::filters( array( 'month' => $month, 'type' => $type, 'service' => $service ) );
        $directory = StreamingServices::directory(); if ( $service && ! isset( $directory[ $service ] ) ) { throw new \InvalidArgumentException( 'Choose a published streaming service.' ); }
        $date = new \DateTimeImmutable( $month . '-01', wp_timezone() ); ReleaseCalendar::month( $date->modify( '+' . ( $months - 1 ) . ' months' )->format( 'Y-m' ) );
        $results = array();
        return Database::lock( 'release-pages:' . get_current_blog_id(), static function () use ( $date, $months, $type, $service, $directory ) {
            $results = array();
            for ( $i = 0; $i < $months; ++$i ) {
                $current = $date->modify( '+' . $i . ' months' ); $month = $current->format( 'Y-m' ); $key = hash( 'sha256', $month . '|' . $type . '|' . $service );
                $existing = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => '_msc_release_page_key', 'meta_value' => $key, 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
                if ( $existing ) { if ( 'page' === $existing[0]->post_type ) { if ( ! current_user_can( 'edit_post', $existing[0]->ID ) ) { throw new \RuntimeException( 'An existing guide requires an editor with permission to move it to Posts.' ); } wp_update_post( array( 'ID' => $existing[0]->ID, 'post_type' => 'post', 'post_parent' => 0, 'post_status' => in_array( $existing[0]->post_status, array( 'publish', 'future' ), true ) ? 'pending' : $existing[0]->post_status ) ); } $results[] = array( 'id' => $existing[0]->ID, 'created' => false ); continue; }
                $label = trim( ( $service ? $directory[ $service ]['title'] . ' ' : '' ) . ( $type ? Schema::get( $type )['label'] : '' ) );
                $title = trim( wp_date( 'F Y', $current->getTimestamp() ) . ' ' . $label . ' Releases' );
                $shortcode = '[msc_upcoming_releases month="' . $month . '" type="' . esc_attr( $type ) . '" service="' . esc_attr( $service ) . '"]';
                $id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'pending', 'post_title' => $title, 'post_name' => sanitize_title( $month . ( $label ? '-' . $label : '' ) ), 'post_content' => $shortcode, 'meta_input' => array( '_msc_upcoming_releases' => '1', '_msc_release_page_key' => $key, '_msc_release_page_config' => array( 'month' => $month, 'type' => $type, 'service' => $service ) ) ), true );
                if ( is_wp_error( $id ) ) { throw new \RuntimeException( 'Could not create a release guide for review.' ); }
                $results[] = array( 'id' => (int) $id, 'created' => true );
            }
            return $results;
        } );
    }
    public static function page(): void {
        if ( ! self::allowed() ) { wp_die( 'Release-post editing permission is required.', 'Release Pages', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><h1>Release Posts</h1><p>Generate monthly release guides as regular Posts marked Pending Review. Editors review and publish them from the normal Posts list. Listings update automatically when dates, titles, artwork, services or publication states change. Repeating a generation keeps existing pages, publication status and editorial changes. Restore trashed guides from Posts → Trash.</p>';
        if ( isset( $_GET['generated'] ) ) { echo '<div class="notice notice-success"><p>Release posts were queued for review. Existing posts were retained.</p></div>'; }
        echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=pending&msc_release_guides=1' ) ) . '">Review pending release posts</a></p>';
        echo '<p><a class="button" href="' . esc_url( UpcomingReleases::hub_url() ) . '">View upcoming releases</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_generate_release_pages">'; wp_nonce_field( 'msc_generate_release_pages' );
        echo '<table class="form-table"><tr><th><label for="release-start">First month</label></th><td><input id="release-start" type="month" name="month" value="' . esc_attr( wp_date( 'Y-m' ) ) . '" required></td></tr><tr><th><label for="release-months">Number of months</label></th><td><input id="release-months" type="number" name="months" min="1" max="12" value="6" required></td></tr><tr><th><label for="release-type">Content type</label></th><td><select id="release-type" name="type"><option value="">All enabled release types</option>';
        foreach ( UpcomingReleases::types() as $type => $fields ) { echo '<option value="' . esc_attr( $type ) . '">' . esc_html( Schema::get( $type )['label'] ) . '</option>'; }
        echo '</select></td></tr><tr><th><label for="release-service">Streaming service</label></th><td><select id="release-service" name="service"><option value="">All services and other releases</option>';
        foreach ( StreamingServices::directory() as $service ) { echo '<option value="' . esc_attr( $service['uuid'] ) . '">' . esc_html( $service['title'] ) . '</option>'; }
        echo '</select><p class="description">Service guides list assigned TV/episode dates and movie streaming dates. They do not imply regional availability or a subscription offer.</p></td></tr></table>'; submit_button( 'Generate release posts for review' ); echo '</form><h2>Monthly release posts</h2><table class="widefat striped"><thead><tr><th>Post</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        $pages = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'meta_key' => '_msc_release_page_key', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
        foreach ( $pages as $page ) { if ( ! current_user_can( 'edit_post', $page->ID ) ) { $complete = false; continue; } echo '<tr><td>' . esc_html( $page->post_title ) . '</td><td>' . esc_html( $page->post_status ) . '</td><td><a href="' . esc_url( get_edit_post_link( $page->ID, '' ) ) . '">Edit</a> · <a href="' . esc_url( get_permalink( $page->ID ) ) . '">View</a></td></tr>'; }
        echo '</tbody></table><p>Streaming service profiles and logos are managed under Shared Content → Streaming Services. Assign services through the linked service fields on Movies, TV Shows and Episodes.</p></div>';
    }
    public static function boot(): void {
        add_action( 'admin_init', array( self::class, 'upgrade' ) );
        add_action( 'restrict_manage_posts', static function ( $type ) { if ( 'post' === $type ) { echo '<select name="msc_release_guides" aria-label="Release guides"><option value="">All posts</option><option value="1"' . selected( '1', is_string( $_GET['msc_release_guides'] ?? null ) ? $_GET['msc_release_guides'] : '', false ) . '>Release guides</option></select>'; } } );
        add_action( 'pre_get_posts', static function ( $query ) {
            if ( ! is_admin() || ! $query->is_main_query() || 'post' !== ( $query->get( 'post_type' ) ?: 'post' ) || '1' !== ( $_GET['msc_release_guides'] ?? '' ) ) { return; }
            $meta = (array) $query->get( 'meta_query' ); $condition = array( 'key' => '_msc_release_page_key', 'compare' => 'EXISTS' ); $query->set( 'meta_query', $meta ? array( 'relation' => 'AND', $meta, $condition ) : array( $condition ) );
        } );
        add_action( 'admin_menu', static function () { if ( self::allowed() ) { add_submenu_page( 'msc', 'Release Posts', 'Release Posts', 'edit_posts', 'msc-release-pages', array( self::class, 'page' ) ); } } );
        add_action( 'admin_post_msc_generate_release_pages', static function () {
            check_admin_referer( 'msc_generate_release_pages' );
            try {
                $values = array(); foreach ( array( 'month', 'months', 'type', 'service' ) as $key ) { $value = $_POST[ $key ] ?? ''; if ( ! is_string( $value ) ) { throw new \InvalidArgumentException( 'Invalid release-page settings.' ); } $values[ $key ] = sanitize_text_field( wp_unslash( $value ) ); }
                if ( ! ctype_digit( $values['months'] ) ) { throw new \InvalidArgumentException( 'Choose 1–12 months.' ); }
                self::generate( $values['month'], (int) $values['months'], $values['type'], $values['service'] );
                wp_safe_redirect( admin_url( 'admin.php?page=msc-release-pages&generated=1' ) ); exit;
            } catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Release Pages', array( 'response' => self::allowed() ? 400 : 403, 'back_link' => true ) ); }
        } );
    }
}
