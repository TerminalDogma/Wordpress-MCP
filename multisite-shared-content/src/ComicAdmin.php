<?php
namespace MSC;

final class ComicAdmin {
    public static function url( array $r, bool $source = false ): string { return add_query_arg( array( 'page' => 'msc-comic-issues', 'record' => $r['uuid'], 'source' => $source ? '1' : '' ), admin_url( 'admin.php' ) ); }
    public static function native_url( array $r ): string { return add_query_arg( array( 'post_type' => 'msc_comic_issue', 'msc_comic_parent' => $r['uuid'] ), admin_url( 'edit.php' ) ); }
    public static function context( array $r ): void {
        if ( ! Comics::is_type( $r['type'] ) ) { return; }
        if ( 'msc_comic_issue' === $r['type'] ) {
            $parent = ! empty( $r['data']['parent_comic'] ) ? Plugin::store()->get( $r['data']['parent_comic'] ) : null;
            if ( $parent ) { echo '<p class="msc-comic-context"><strong>Comic Book: ' . esc_html( $parent['data']['title'] ?? '' ) . '</strong> · <a href="' . esc_url( self::url( $parent ) ) . '">Manage sibling issues</a></p>'; }
            return;
        }
        echo '<p class="msc-comic-context"><a class="button" href="' . esc_url( self::url( $r ) ) . '">Manage issues</a> ';
        echo '<a class="button" href="' . esc_url( self::native_url( $r ) ) . '">Bulk edit / publish issues</a> ';
        if ( current_user_can( 'msc_create_global' ) ) { echo '<a class="button" href="' . esc_url( Comics::add_url( $r ) ) . '">Add issue</a> '; }
        if ( 'msc_comic' === $r['type'] && ! empty( $r['data']['external_ids']['gcd'] ) && OpenImport::allowed() ) { echo '<a class="button" href="' . esc_url( self::url( $r, true ) ) . '">Import issues from GCD</a>'; }
        echo '</p>';
    }
    public static function boot(): void {
        add_action( 'pre_get_posts', static function ( $q ) {
            if ( ! is_admin() || ! $q->is_main_query() || 'msc_comic_issue' !== $q->get( 'post_type' ) || ! isset( $_GET['msc_comic_parent'] ) || ! is_string( $_GET['msc_comic_parent'] ) ) { return; }
            $r = Plugin::store()->get( sanitize_text_field( wp_unslash( $_GET['msc_comic_parent'] ) ) );
            if ( ! $r || ! in_array( $r['type'], array( 'msc_comic', 'msc_comic_arc' ), true ) || ! Schema::enabled( $r['type'] ) ) { $q->set( 'post__in', array( 0 ) ); return; }
            $meta = (array) $q->get( 'meta_query' ); $condition = array( 'key' => 'msc_comic' === $r['type'] ? 'msc_parent_comic' : 'msc_story_arc', 'value' => $r['uuid'] ); $q->set( 'meta_query', $meta ? array( 'relation' => 'AND', $meta, $condition ) : array( $condition ) );
        } );
        add_action( 'restrict_manage_posts', static function ( $type ) { if ( 'msc_comic_issue' === $type && isset( $_GET['msc_comic_parent'] ) && is_string( $_GET['msc_comic_parent'] ) ) { echo '<input type="hidden" name="msc_comic_parent" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET['msc_comic_parent'] ) ) ) . '">'; } } );
        add_filter( 'manage_msc_comic_issue_posts_columns', static function ( $columns ) { $columns['comic_parent'] = 'Comic Book'; $columns['comic_number'] = 'Issue'; $columns['comic_arc'] = 'Story Arc / position'; return $columns; } );
        add_action( 'manage_msc_comic_issue_posts_custom_column', static function ( $column, $id ) {
            if ( 'comic_number' === $column ) { echo esc_html( get_post_meta( $id, 'msc_issue_number', true ) ); }
            elseif ( in_array( $column, array( 'comic_parent', 'comic_arc' ), true ) ) { $uuid = get_post_meta( $id, 'comic_parent' === $column ? 'msc_parent_comic' : 'msc_story_arc', true ); $r = $uuid ? Plugin::store()->resolved( $uuid ) : null; if ( $r ) { echo '<a href="' . esc_url( self::url( $r ) ) . '">' . esc_html( $r['data']['title'] ?? '' ) . '</a>'; if ( 'comic_arc' === $column && ( $part = get_post_meta( $id, 'msc_arc_position', true ) ) ) { echo ' · Part ' . esc_html( $part ); } } }
        }, 10, 2 );
        add_filter( 'manage_msc_comic_posts_columns', static function ( $columns ) { $columns['comic_run'] = 'Run / volume'; $columns['comic_publisher'] = 'Publisher'; return $columns; } );
        add_action( 'manage_msc_comic_posts_custom_column', static function ( $column, $id ) { if ( 'comic_run' === $column ) { echo esc_html( implode( ' · ', array_filter( array( get_post_meta( $id, 'msc_start_year', true ), get_post_meta( $id, 'msc_volume', true ) ), 'strlen' ) ) ); } elseif ( 'comic_publisher' === $column ) { echo esc_html( get_post_meta( $id, 'msc_publisher', true ) ); } }, 10, 2 );
        add_action( 'admin_init', array( Comics::class, 'enroll' ) );
        add_action( 'admin_menu', static function () { if ( Schema::enabled( 'msc_comic' ) && Schema::enabled( 'msc_comic_issue' ) ) { add_submenu_page( 'edit.php?post_type=msc_comic', 'Comic Issues by Comic Book', 'Issues by Comic Book', 'edit_posts', 'msc-comic-issues', array( self::class, 'page' ) ); } } );
        add_filter( 'post_row_actions', static function ( $actions, $post ) { if ( in_array( $post->post_type, array( 'msc_comic', 'msc_comic_arc' ), true ) && ( $r = Plugin::store()->by_post( $post->ID ) ) ) { $actions['msc_comic_issues'] = '<a href="' . esc_url( self::url( $r ) ) . '">Issues</a>'; } return $actions; }, 10, 2 );
        add_action( 'admin_post_msc_comic_batch', static function () {
            check_admin_referer( 'msc_comic_batch' );
            try { $token = ComicImport::start( sanitize_text_field( wp_unslash( $_POST['parent'] ?? '' ) ), wp_unslash( $_POST['issues'] ?? array() ), ! empty( $_POST['refresh'] ) ); wp_safe_redirect( add_query_arg( array( 'page' => 'msc-import', 'job' => $token ), admin_url( 'admin.php' ) ) ); exit; }
            catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Comic issue import', array( 'response' => $e instanceof SourceError ? $e->status : 400, 'back_link' => true ) ); }
        } );
        add_action( 'admin_enqueue_scripts', static function () { if ( 'msc-comic-issues' === ( $_GET['page'] ?? '' ) ) { wp_enqueue_script( 'msc-comic-admin', plugins_url( 'assets/comic-admin.js', MSC_FILE ), array(), MSC_VERSION, true ); } } );
    }
    public static function page(): void {
        if ( ! current_user_can( 'edit_posts' ) || ! Schema::enabled( 'msc_comic_issue' ) ) { wp_die( 'Comic Issues are unavailable on this site.' ); }
        echo '<div class="wrap msc-comic-admin"><h1>Comic Issues by Comic Book</h1>';
        $uuid = isset( $_GET['record'] ) && is_string( $_GET['record'] ) ? sanitize_text_field( wp_unslash( $_GET['record'] ) ) : '';
        if ( ! $uuid ) {
            echo '<p>Select a Comic Book to manage its issues. Story Arcs have their own cross-series reading order.</p><table class="widefat striped"><thead><tr><th>Comic Book</th><th>Actions</th></tr></thead><tbody>';
            $page = max( 1, absint( $_GET['comic_page'] ?? 1 ) );
            $q = new \WP_Query( array( 'post_type' => 'msc_comic', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 25, 'paged' => $page, 'orderby' => 'title', 'order' => 'ASC' ) );
            foreach ( $q->posts as $post ) { $r = Plugin::store()->by_post( $post->ID ); if ( $r && 'active' === $r['state'] ) { echo '<tr><td>' . esc_html( $post->post_title ) . '</td><td><a class="button" href="' . esc_url( self::url( $r ) ) . '">Manage issues</a></td></tr>'; } }
            echo '</tbody></table>'; if ( $page > 1 ) { echo '<a class="button" href="' . esc_url( add_query_arg( 'comic_page', $page - 1 ) ) . '">Previous page</a> '; } if ( $page < $q->max_num_pages ) { echo '<a class="button" href="' . esc_url( add_query_arg( 'comic_page', $page + 1 ) ) . '">Next page</a>'; } echo '</div>'; return;
        }
        $owner = Plugin::store()->resolved( $uuid );
        if ( ! $owner || ! in_array( $owner['type'], array( 'msc_comic', 'msc_comic_arc' ), true ) || ! Schema::enabled( $owner['type'] ) || 'active' !== $owner['state'] ) { wp_die( 'The Comic Book or Story Arc is unavailable.' ); }
        echo '<h2>' . esc_html( $owner['data']['title'] ) . '</h2>'; self::context( $owner );
        if ( ! empty( $_GET['source'] ) ) { self::source( $owner ); echo '</div>'; return; }
        $data = Comics::issue_page( $owner, $_GET, true );
        echo '<p>' . esc_html( $data['total'] ) . ' issues. Counts include drafts; public pages show only published issues.</p><table class="widefat striped"><thead><tr><th>Issue</th><th>Title</th><th>Story Arc / position</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $data['issues'] as $r ) {
            $arc = ! empty( $r['data']['story_arc'] ) ? Plugin::store()->resolved( $r['data']['story_arc'] ) : null;
            echo '<tr><td>' . esc_html( $r['data']['issue_number'] ?? '' ) . '</td><td>' . esc_html( $r['data']['title'] ) . '</td><td>' . esc_html( $arc ? ( $arc['data']['title'] ?? '' ) . ( isset( $r['data']['arc_position'] ) ? ' · Part ' . $r['data']['arc_position'] : '' ) : '' ) . '</td><td>' . esc_html( get_post_status( $r['mapping']['post_id'] ) ) . '</td><td><a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] ) ) . '">Manage fields</a> · <a href="' . esc_url( get_edit_post_link( $r['mapping']['post_id'], '' ) ) . '">Edit / publish</a></td></tr>';
        }
        echo '</tbody></table><p>Page ' . esc_html( $data['page'] . ' of ' . $data['pages'] ) . '</p>';
        foreach ( array( $data['page'] - 1 => 'Previous', $data['page'] + 1 => 'Next' ) as $page => $label ) { if ( $page >= 1 && $page <= $data['pages'] ) { echo '<a class="button" href="' . esc_url( add_query_arg( 'comic_page', $page ) ) . '">' . esc_html( $label ) . '</a> '; } } echo '</div>';
    }
    private static function source( array $owner ): void {
        if ( ! OpenImport::allowed() || 'msc_comic' !== $owner['type'] ) { wp_die( 'Global import permission and a Comic Book are required.' ); }
        try { $all = ComicSource::issues( (string) ( $owner['data']['external_ids']['gcd'] ?? '' ) ); }
        catch ( \Throwable $e ) { echo '<div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div>'; return; }
        $page = max( 1, min( max( 1, (int) ceil( count( $all ) / 50 ) ), absint( $_GET['comic_page'] ?? 1 ) ) );
        echo '<p>Select issues to import. New issues follow your publication defaults. Existing identities are reused. Arc membership and reading-order positions can be added after import.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_comic_batch"><input type="hidden" name="parent" value="' . esc_attr( $owner['uuid'] ) . '">'; wp_nonce_field( 'msc_comic_batch' );
        echo '<table class="widefat striped"><thead><tr><th><input type="checkbox" data-comic-check-all aria-label="Select all issues on this page"></th><th>GCD issue</th><th>Preview</th></tr></thead><tbody>';
        foreach ( array_slice( $all, ( $page - 1 ) * 50, 50 ) as $row ) {
            $url = add_query_arg( array( 'page' => 'msc-import', 'kind' => 'msc_comic_issue', 'provider' => 'gcd', 'id' => $row['id'], 'parent' => $owner['uuid'] ), admin_url( 'admin.php' ) );
            echo '<tr><td><input type="checkbox" name="issues[]" value="' . esc_attr( $row['id'] ) . '" aria-label="Select issue ' . esc_attr( $row['title'] ) . '"></td><td>' . esc_html( $row['title'] ) . '</td><td><a href="' . esc_url( $url ) . '">Preview selected fields</a></td></tr>';
        }
        echo '</tbody></table><p><label><input type="checkbox" name="refresh" value="1"> Refresh existing issues as well (local overrides remain in effect)</label></p>'; submit_button( 'Import selected issues' ); echo '</form>';
        foreach ( array( $page - 1 => 'Previous page', $page + 1 => 'Next page' ) as $number => $label ) { if ( $number >= 1 && $number <= (int) ceil( count( $all ) / 50 ) ) { echo '<a class="button" href="' . esc_url( add_query_arg( 'comic_page', $number ) ) . '">' . esc_html( $label ) . '</a> '; } }
    }
}
