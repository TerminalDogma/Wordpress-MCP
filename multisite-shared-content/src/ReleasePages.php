<?php
namespace MSC;

/** Creates ordinary, editable WordPress pages whose listings remain live. */
final class ReleasePages {
    public static function allowed(): bool { return current_user_can( 'edit_pages' ) && current_user_can( 'publish_pages' ) && ( current_user_can( 'msc_override_local' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_create_global' ) ); }
    public static function generate( string $month, int $months, string $type = '', string $service = '' ): array {
        if ( ! self::allowed() ) { throw new \RuntimeException( 'Page publication and shared-content editing permission are required.' ); }
        if ( $months < 1 || $months > 12 ) { throw new \InvalidArgumentException( 'Generate between 1 and 12 monthly pages.' ); }
        $f = UpcomingReleases::filters( array( 'month' => $month, 'type' => $type, 'service' => $service ) );
        $directory = StreamingServices::directory(); if ( $service && ! isset( $directory[ $service ] ) ) { throw new \InvalidArgumentException( 'Choose a published streaming service.' ); }
        $date = new \DateTimeImmutable( $month . '-01', wp_timezone() ); ReleaseCalendar::month( $date->modify( '+' . ( $months - 1 ) . ' months' )->format( 'Y-m' ) );
        $hub = UpcomingReleases::hub(); $results = array();
        return Database::lock( 'release-pages:' . get_current_blog_id(), static function () use ( $date, $months, $type, $service, $directory, $hub ) {
            $results = array();
            for ( $i = 0; $i < $months; ++$i ) {
                $current = $date->modify( '+' . $i . ' months' ); $month = $current->format( 'Y-m' ); $key = hash( 'sha256', $month . '|' . $type . '|' . $service );
                $existing = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'meta_key' => '_msc_release_page_key', 'meta_value' => $key, 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
                if ( $existing ) { $results[] = array( 'id' => $existing[0]->ID, 'created' => false ); continue; }
                $label = trim( ( $service ? $directory[ $service ]['title'] . ' ' : '' ) . ( $type ? Schema::get( $type )['label'] : '' ) );
                $title = trim( wp_date( 'F Y', $current->getTimestamp() ) . ' ' . $label . ' Releases' );
                $shortcode = '[msc_upcoming_releases month="' . $month . '" type="' . esc_attr( $type ) . '" service="' . esc_attr( $service ) . '"]';
                $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $hub, 'post_title' => $title, 'post_name' => sanitize_title( $month . ( $label ? '-' . $label : '' ) ), 'post_content' => $shortcode, 'meta_input' => array( '_msc_upcoming_releases' => '1', '_msc_release_page_key' => $key, '_msc_release_page_config' => array( 'month' => $month, 'type' => $type, 'service' => $service ) ) ), true );
                if ( is_wp_error( $id ) ) { throw new \RuntimeException( 'Could not create a monthly release page.' ); }
                $results[] = array( 'id' => (int) $id, 'created' => true );
            }
            return $results;
        } );
    }
    public static function page(): void {
        if ( ! self::allowed() ) { wp_die( 'Release-page publication permission is required.', 'Release Pages', array( 'response' => 403 ) ); }
        UpcomingReleases::hub();
        echo '<div class="wrap"><h1>Release Pages</h1><p>Create monthly release guides from published catalog dates. Listings update automatically when dates, titles, artwork, services or publication states change. Repeating a generation keeps existing pages, publication status and editorial changes. Restore trashed guides from Pages → Trash.</p>';
        if ( isset( $_GET['generated'] ) ) { echo '<div class="notice notice-success"><p>Release pages are ready. Existing pages were retained.</p></div>'; }
        echo '<p><a class="button" href="' . esc_url( UpcomingReleases::hub_url() ) . '">View upcoming releases</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_generate_release_pages">'; wp_nonce_field( 'msc_generate_release_pages' );
        echo '<table class="form-table"><tr><th><label for="release-start">First month</label></th><td><input id="release-start" type="month" name="month" value="' . esc_attr( wp_date( 'Y-m' ) ) . '" required></td></tr><tr><th><label for="release-months">Number of months</label></th><td><input id="release-months" type="number" name="months" min="1" max="12" value="6" required></td></tr><tr><th><label for="release-type">Content type</label></th><td><select id="release-type" name="type"><option value="">All enabled release types</option>';
        foreach ( UpcomingReleases::types() as $type => $fields ) { echo '<option value="' . esc_attr( $type ) . '">' . esc_html( Schema::get( $type )['label'] ) . '</option>'; }
        echo '</select></td></tr><tr><th><label for="release-service">Streaming service</label></th><td><select id="release-service" name="service"><option value="">All services and other releases</option>';
        foreach ( StreamingServices::directory() as $service ) { echo '<option value="' . esc_attr( $service['uuid'] ) . '">' . esc_html( $service['title'] ) . '</option>'; }
        echo '</select><p class="description">Service guides list assigned TV/episode dates and movie streaming dates. They do not imply regional availability or a subscription offer.</p></td></tr></table>'; submit_button( 'Generate monthly release pages' ); echo '</form><h2>Monthly release pages</h2><table class="widefat striped"><thead><tr><th>Page</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        $pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'meta_key' => '_msc_release_page_key', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
        foreach ( $pages as $page ) { if ( ! current_user_can( 'edit_post', $page->ID ) ) { continue; } echo '<tr><td>' . esc_html( $page->post_title ) . '</td><td>' . esc_html( $page->post_status ) . '</td><td><a href="' . esc_url( get_edit_post_link( $page->ID, '' ) ) . '">Edit</a> · <a href="' . esc_url( get_permalink( $page->ID ) ) . '">View</a></td></tr>'; }
        echo '</tbody></table><p>Streaming service profiles and logos are managed under Shared Content → Streaming Services. Assign services through the linked service fields on Movies, TV Shows and Episodes.</p></div>';
    }
    public static function boot(): void {
        add_action( 'admin_menu', static function () { if ( self::allowed() ) { add_submenu_page( 'msc', 'Release Pages', 'Release Pages', 'edit_pages', 'msc-release-pages', array( self::class, 'page' ) ); } } );
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
