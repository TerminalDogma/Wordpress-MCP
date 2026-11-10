<?php
namespace MSC;

/** Selected native list rows become resumable, permission-checked publication jobs. */
final class BulkPublication {
    private static function digest( \WP_Post $p ): string { return hash( 'sha256', wp_json_encode( array( $p->post_author, $p->post_date_gmt, $p->post_status, $p->post_title, $p->post_content, $p->post_excerpt, $p->post_password ) ) ); }
    private static function authorize( \WP_Post $post, string $scope ): ?array {
        $object = get_post_type_object( $post->post_type );
        if ( ! $object || ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( $object->cap->publish_posts ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) { throw new \RuntimeException( 'This post cannot be published by this user.' ); }
        $r = Plugin::store()->by_post( $post->ID );
        if ( isset( Schema::all()[ $post->post_type ] ) ) {
            if ( ! $r ) { throw new \RuntimeException( 'This shared post is not connected to a network record.' ); }
            Publication::require( $scope, $r ); $m = Plugin::store()->mapping( (int) $r['id'] );
            if ( ! $m || $m['suppressed'] || in_array( $m['status'], array( 'removed', 'unpublished' ), true ) ) { throw new \RuntimeException( 'Restore this locally removed or unpublished record before bulk publishing.' ); }
        } elseif ( 'global' === $scope ) { throw new \RuntimeException( 'Global publication is available only for shared custom post types.' ); }
        return $r;
    }
    public static function start( array $ids, string $scope, string $type, string $return_url = '' ): string {
        if ( ! in_array( $scope, array( 'site', 'global' ), true ) || ! post_type_exists( $type ) || ! $ids || count( $ids ) > 5000 ) { throw new \InvalidArgumentException( 'Select between 1 and 5,000 posts and a valid publication scope.' ); }
        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) ); $items = array();
        foreach ( $ids as $id ) {
            $p = get_post( $id ); if ( ! $p || $type !== $p->post_type ) { throw new \InvalidArgumentException( 'The selection contains a different or missing post type.' ); }
            $r = self::authorize( $p, $scope ); $m = $r ? Plugin::store()->mapping( (int) $r['id'] ) : null;
            $items[] = array( 'id' => $id, 'digest' => self::digest( $p ), 'record' => $r['id'] ?? 0, 'version' => (int) ( $r['version'] ?? 0 ), 'local_version' => (int) ( $m['local_version'] ?? 0 ) );
        }
        $token = wp_generate_uuid4(); $job = array( 'owner' => array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ), 'type' => $type, 'scope' => $scope, 'return_url' => wp_validate_redirect( $return_url, add_query_arg( 'post_type', $type, admin_url( 'edit.php' ) ) ), 'items' => $items, 'index' => 0, 'results' => array() );
        set_site_transient( 'msc_bulk_publish_' . $token, $job, DAY_IN_SECONDS ); return $token;
    }
    public static function job( string $token ): array {
        if ( ! wp_is_uuid( $token ) ) { throw new \InvalidArgumentException( 'Invalid publication job.' ); }
        $job = get_site_transient( 'msc_bulk_publish_' . $token );
        if ( ! is_array( $job ) || $job['owner'] !== array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ) ) { throw new \RuntimeException( 'This publication job expired or belongs to another user or site.' ); }
        return $job;
    }
    public static function step( string $token ): array {
        return Database::lock( 'bulk-publish:' . $token, static function () use ( $token ) {
            $job = self::job( $token ); $end = min( count( $job['items'] ), $job['index'] + 10 );
            while ( $job['index'] < $end ) {
                $item = $job['items'][ $job['index'] ]; $title = 'Post ' . $item['id'];
                try {
                    $p = get_post( $item['id'] ); if ( ! $p || $p->post_type !== $job['type'] ) { throw new \RuntimeException( 'The post was deleted or its type changed.' ); }
                    $r = self::authorize( $p, $job['scope'] ); $title = $p->post_title;
                    if ( self::digest( $p ) !== $item['digest'] || $r && ( (int) $r['id'] !== (int) $item['record'] || (int) $r['version'] !== $item['version'] ) ) { throw new \RuntimeException( 'Changed after selection; review and select it again.' ); }
                    if ( $r ) { Publication::publish( (int) $r['id'], $job['scope'], $item['local_version'], $item['version'] ); }
                    else { $changes = array( 'ID' => $p->ID, 'post_status' => 'publish' ); if ( 'future' === $p->post_status ) { $changes['post_date'] = current_time( 'mysql' ); $changes['post_date_gmt'] = current_time( 'mysql', true ); } $result = wp_update_post( $changes, true ); if ( is_wp_error( $result ) ) { throw new \RuntimeException( $result->get_error_message() ); } }
                    $status = 'Published';
                } catch ( \Throwable $e ) { $status = 'Skipped: ' . $e->getMessage(); }
                $job['results'][] = array( 'id' => $item['id'], 'title' => $title, 'status' => $status ); ++$job['index'];
            }
            set_site_transient( 'msc_bulk_publish_' . $token, $job, DAY_IN_SECONDS ); return $job;
        } );
    }
    public static function page(): void {
        try { $token = is_string( $_GET['job'] ?? null ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : ''; $job = self::job( $token ); }
        catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Bulk Publish', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><h1>Bulk publication</h1><p>Publication runs in batches of ten. Changed records and local removals are skipped for review. Existing field overrides remain intact.</p><section id="msc-bulk-publish" data-endpoint="' . esc_url( rest_url( 'msc/v1/bulk-publication/' . $token ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '" data-total="' . count( $job['items'] ) . '"><p class="msc-bulk-status" role="status">' . (int) $job['index'] . ' / ' . count( $job['items'] ) . ' processed</p><progress max="' . count( $job['items'] ) . '" value="' . (int) $job['index'] . '"></progress><p><button class="button button-primary" data-bulk-start>Start / resume publishing</button> <button class="button" data-bulk-pause>Pause</button> <a class="button" href="' . esc_url( $job['return_url'] ?? add_query_arg( 'post_type', $job['type'], admin_url( 'edit.php' ) ) ) . '">Return to post list</a></p><ul class="msc-bulk-results">';
        foreach ( $job['results'] as $result ) { echo '<li>' . esc_html( $result['title'] . ' — ' . $result['status'] ) . '</li>'; } echo '</ul></section></div>';
    }
    public static function boot(): void {
        add_action( 'init', static function () {
            foreach ( get_post_types( array( 'show_ui' => true ) ) as $type ) {
                if ( ! in_array( $type, array_merge( array( 'post', 'page', 'msc_review' ), array_keys( Schema::all() ) ), true ) ) { continue; }
                add_filter( 'bulk_actions-edit-' . $type, static function ( $actions ) use ( $type ) {
                    $object = get_post_type_object( $type ); if ( ! current_user_can( $object->cap->publish_posts ) ) { return $actions; }
                    if ( isset( Schema::all()[ $type ] ) ) { if ( ! Schema::enabled( $type ) || ! current_user_can( 'msc_publish_local' ) ) { return $actions; } if ( Publication::global_allowed() ) { $actions['msc_publish_global'] = 'Publish selected globally'; } }
                    $actions['msc_publish_site'] = 'Publish selected on this site'; return $actions;
                } );
                add_filter( 'handle_bulk_actions-edit-' . $type, static function ( $redirect, $action, $ids ) use ( $type ) {
                    if ( ! in_array( $action, array( 'msc_publish_global', 'msc_publish_site' ), true ) ) { return $redirect; }
                    check_admin_referer( 'bulk-posts' );
                    try { $token = self::start( (array) $ids, 'msc_publish_global' === $action ? 'global' : 'site', $type, $redirect ); return add_query_arg( array( 'page' => 'msc-bulk-publish', 'job' => $token ), admin_url( 'admin.php' ) ); }
                    catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Bulk Publish', array( 'response' => 403, 'back_link' => true ) ); }
                }, 10, 3 );
            }
        }, 30 );
        add_action( 'admin_menu', static function () { add_submenu_page( null, 'Bulk Publish', 'Bulk Publish', 'read', 'msc-bulk-publish', array( self::class, 'page' ) ); } );
        add_action( 'admin_enqueue_scripts', static function () { if ( 'msc-bulk-publish' === ( $_GET['page'] ?? '' ) ) { wp_enqueue_script( 'msc-bulk-publish', plugins_url( 'assets/bulk-publication.js', MSC_FILE ), array(), MSC_VERSION, true ); } } );
        add_action( 'rest_api_init', static function () { register_rest_route( 'msc/v1', '/bulk-publication/(?P<token>[a-f0-9-]{36})', array( 'methods' => 'POST', 'permission_callback' => 'is_user_logged_in', 'callback' => static function ( $request ) { try { $job = self::step( $request['token'] ); return array( 'index' => $job['index'], 'total' => count( $job['items'] ), 'results' => $job['results'] ); } catch ( \Throwable $e ) { return new \WP_Error( 'msc_bulk_publication', $e->getMessage(), array( 'status' => 403 ) ); } } ) ); } );
    }
}
