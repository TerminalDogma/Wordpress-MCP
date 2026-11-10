<?php
namespace MSC;

/** Comic runs, individual issues and cross-series reading arcs. */
final class Comics {
    public const TYPES = array( 'msc_comic', 'msc_comic_issue', 'msc_comic_arc' );
    public static function is_type( string $type ): bool { return in_array( $type, self::TYPES, true ); }
    public static function singular( string $type ): string { return array( 'msc_comic' => 'Comic Book', 'msc_comic_issue' => 'Comic Issue', 'msc_comic_arc' => 'Story Arc' )[ $type ] ?? ''; }
    public static function schemas( array $common ): array {
        $shared = $common + array(
            'poster' => array( 'type' => 'media', 'label' => 'Cover image' ),
            'publisher' => array( 'type' => 'text', 'taxonomy' => 'msc_comic_publisher' ),
            'writers' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_comic_creator' ),
            'artists' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_comic_creator' ),
            'genres' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_genre' ),
            'official_website' => array( 'type' => 'url' ), 'external_ids' => array( 'type' => 'json' ),
        );
        $series = $shared + array(
            'story_arcs' => array( 'type' => 'relationship_list', 'target' => 'msc_comic_arc', 'label' => 'Story arcs', 'instructions' => 'Assign arcs to this comic run. Assign each issue to its arc separately and enter its reading-order position.' ),
            'start_year' => array( 'type' => 'integer', 'min' => 0 ), 'end_year' => array( 'type' => 'integer', 'min' => 0 ),
            'volume' => array( 'type' => 'text', 'label' => 'Run / volume' ),
            'publication_format' => array( 'type' => 'text', 'label' => 'Publication format' ),
            'issue_count' => array( 'type' => 'integer', 'min' => 0, 'label' => 'Source issue count' ),
            'country' => array( 'type' => 'text', 'taxonomy' => 'msc_country' ), 'language' => array( 'type' => 'text', 'taxonomy' => 'msc_language' ),
        );
        $issue = $shared + array(
            'parent_comic' => array( 'type' => 'relationship', 'target' => 'msc_comic', 'indexed' => true, 'label' => 'Parent Comic Book' ),
            'issue_number' => array( 'type' => 'text', 'indexed' => true, 'label' => 'Published issue number' ),
            'issue_order' => array( 'type' => 'number', 'indexed' => true, 'label' => 'Optional numeric sort order', 'instructions' => 'Use this to place fractional issues, annuals or variants in the series order.' ),
            'story_arc' => array( 'type' => 'relationship', 'target' => 'msc_comic_arc', 'indexed' => true ),
            'arc_position' => array( 'type' => 'integer', 'min' => 1, 'indexed' => true, 'label' => 'Issue position in story arc', 'instructions' => 'Reading-order position across the whole arc, independent of the published issue number.' ),
            'release_date' => array( 'type' => 'date', 'indexed' => true, 'label' => 'On-sale date' ),
            'cover_date' => array( 'type' => 'text', 'label' => 'Cover date (may be month/year only)' ),
            'volume' => array( 'type' => 'text' ), 'variant' => array( 'type' => 'text' ),
            'page_count' => array( 'type' => 'number', 'min' => 0 ), 'price' => array( 'type' => 'text' ),
            'isbn' => array( 'type' => 'text' ), 'barcode' => array( 'type' => 'text' ),
            'cover_artists' => array( 'type' => 'multiselect', 'taxonomy' => 'msc_comic_creator' ),
        );
        $arc = $shared + array( 'expected_issues' => array( 'type' => 'integer', 'min' => 1, 'label' => 'Total issues in arc (when known)' ) );
        $result = array();
        foreach ( array( 'msc_comic' => array( 'Comic Books', 'comics', $series ), 'msc_comic_issue' => array( 'Comic Issues', 'comic-issues', $issue ), 'msc_comic_arc' => array( 'Story Arcs', 'comic-arcs', $arc ) ) as $type => $row ) {
            $result[ $type ] = array( 'label' => $row[0], 'slug' => $row[1], 'archive' => true, 'noindex' => true, 'default_status' => 'draft', 'fields' => $row[2] );
        }
        return $result;
    }
    /** A one-time enrollment preserves subsequent administrator choices and other sites. */
    public static function enroll(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0 ) ) as $site ) {
            $id = (int) $site->blog_id;
            if ( 'b' !== get_blog_option( $id, 'msc_site_identity', '' ) && 'b' !== NetworkSites::key( $site->path ) || get_blog_option( $id, 'msc_comics_profile_version', 0 ) ) { continue; }
            Schema::participate( $id, array_values( array_unique( array_merge( get_blog_option( $id, 'msc_types', array() ), self::TYPES ) ) ) );
            update_blog_option( $id, 'msc_comics_profile_version', 1 );
        }
    }
    public static function defaults( string $type ): array {
        if ( 'msc_comic_issue' !== $type ) { return array(); }
        $out = array();
        foreach ( array( 'parent_comic' => 'msc_comic', 'story_arc' => 'msc_comic_arc' ) as $field => $target ) {
            $id = isset( $_GET[ $field ] ) && is_string( $_GET[ $field ] ) ? sanitize_text_field( wp_unslash( $_GET[ $field ] ) ) : '';
            $r = $id ? Plugin::store()->get( $id ) : null;
            if ( $r && $target === $r['type'] && 'active' === $r['state'] && Schema::enabled( $target ) ) { $out[ $field ] = $r['uuid']; }
        }
        return $out;
    }
    public static function add_url( array $record ): string {
        return add_query_arg( array( 'page' => 'msc', 'type' => 'msc_comic_issue', 'new' => 1, 'msc_comic' === $record['type'] ? 'parent_comic' : 'story_arc' => $record['uuid'] ), admin_url( 'admin.php' ) );
    }
    public static function admin_allowed(): bool { return current_user_can( 'msc_override_local' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_create_global' ); }
    /** Explicit book assignments also work before any issues have been published. */
    public static function assigned_arcs( array $book, bool $admin = false ): array {
        $out = array();
        foreach ( $book['data']['story_arcs'] ?? array() as $uuid ) {
            $arc = $admin ? Plugin::store()->resolved( $uuid ) : Presentation::record( $uuid );
            if ( $arc && 'msc_comic_arc' === $arc['type'] && 'active' === $arc['state'] && Schema::enabled( 'msc_comic_arc' ) ) { $out[ $uuid ] = $arc; }
        }
        return $out;
    }
    /** Query only the selected parent's projections; drafts/private targets never enter public lists. */
    public static function issue_page( array $owner, array $input = array(), bool $admin = false ): array {
        global $wpdb;
        $empty = array( 'issues' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'arc' => '', 'arcs' => array() );
        if ( $admin && ! self::admin_allowed() || ! Schema::enabled( 'msc_comic_issue' ) || ! in_array( $owner['type'], array( 'msc_comic', 'msc_comic_arc' ), true ) ) { return $empty; }
        $key = 'msc_comic' === $owner['type'] ? 'msc_parent_comic' : 'msc_story_arc';
        $maps = Database::table( 'maps' ); $records = Database::table( 'records' );
        $from = "$wpdb->posts p INNER JOIN $maps m ON m.post_id=p.ID AND m.site_id=" . (int) get_current_blog_id() . " INNER JOIN $records r ON r.id=m.record_id INNER JOIN $wpdb->postmeta parent ON parent.post_id=p.ID AND parent.meta_key='$key' LEFT JOIN $wpdb->postmeta arc ON arc.post_id=p.ID AND arc.meta_key='msc_story_arc'";
        $where = $wpdb->prepare( "p.post_type='msc_comic_issue' AND r.state='active' AND m.suppressed=0 AND parent.meta_value=%s", $owner['uuid'] );
        $where .= $admin ? " AND p.post_status NOT IN ('trash','auto-draft')" : " AND p.post_status='publish' AND p.post_password='' AND m.status='publish'";
        $arcs = array();
        if ( 'msc_comic' === $owner['type'] ) {
            foreach ( self::assigned_arcs( $owner, $admin ) as $uuid => $arc ) { $arcs[ $uuid ] = $arc['data']['title'] ?? ''; }
            foreach ( $wpdb->get_col( "SELECT DISTINCT arc.meta_value FROM $from WHERE $where AND arc.meta_value<>'' LIMIT 500" ) as $uuid ) {
                $arc = $admin ? Plugin::store()->resolved( $uuid ) : Presentation::record( $uuid );
                if ( $arc && Schema::enabled( 'msc_comic_arc' ) && 'msc_comic_arc' === $arc['type'] && 'active' === $arc['state'] ) { $arcs[ $uuid ] = $arc['data']['title'] ?? ''; }
            }
        }
        natcasesort( $arcs ); $selected = isset( $input['comic_arc'] ) && is_string( $input['comic_arc'] ) && isset( $arcs[ $input['comic_arc'] ] ) ? $input['comic_arc'] : '';
        if ( $selected ) { $where .= $wpdb->prepare( ' AND arc.meta_value=%s', $selected ); }
        $total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT r.id) FROM $from WHERE $where" ); $pages = max( 1, (int) ceil( $total / 50 ) );
        $page = isset( $input['comic_page'] ) && is_scalar( $input['comic_page'] ) ? max( 1, min( $pages, absint( $input['comic_page'] ) ) ) : 1;
        $order_key = 'msc_comic_arc' === $owner['type'] || $selected ? 'msc_arc_position' : 'msc_issue_order';
        $from .= " LEFT JOIN $wpdb->postmeta ord ON ord.post_id=p.ID AND ord.meta_key='$order_key' LEFT JOIN $wpdb->postmeta num ON num.post_id=p.ID AND num.meta_key='msc_issue_number'";
        $issue_number = "TRIM(LEADING '#' FROM TRIM(num.meta_value))";
        $fallback = 'msc_comic_arc' === $owner['type'] || $selected ? '' : " WHEN $issue_number REGEXP '^-?[0-9]+([.][0-9]+)?$' THEN CAST($issue_number AS DECIMAL(14,4))";
        $number = "CASE WHEN ord.meta_value REGEXP '^-?[0-9]+([.][0-9]+)?$' THEN CAST(ord.meta_value AS DECIMAL(14,4))" . $fallback . ' ELSE NULL END';
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT r.id,$number AS issue_order,num.meta_value AS issue_number FROM $from WHERE $where ORDER BY issue_order IS NULL,issue_order,issue_number,r.id LIMIT 50 OFFSET %d", ( $page - 1 ) * 50 ) );
        $items = array(); foreach ( $ids as $id ) { $r = $admin ? Plugin::store()->resolved( (int) $id ) : Presentation::record( (int) $id ); if ( $r ) { $items[] = $r; } }
        return array( 'issues' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'arc' => $selected, 'arcs' => $arcs );
    }
    public static function relationship( array $issue, string $field ): ?array {
        $uuid = $issue['data'][ $field ] ?? ''; $r = is_string( $uuid ) && $uuid ? Presentation::record( $uuid ) : null;
        return $r && ( array( 'parent_comic' => 'msc_comic', 'story_arc' => 'msc_comic_arc' )[ $field ] ?? '' ) === $r['type'] ? $r : null;
    }
    public static function arc_label( array $issue ): string {
        $arc = self::relationship( $issue, 'story_arc' ); if ( ! $arc ) { return ''; }
        $label = $arc['data']['title']; $position = $issue['data']['arc_position'] ?? null;
        if ( is_numeric( $position ) && $position >= 1 ) { $label .= ' · Part ' . $position; $count = $arc['data']['expected_issues'] ?? null; if ( is_numeric( $count ) && $count >= $position ) { $label .= ' of ' . $count; } }
        return $label;
    }
    public static function boot(): void { ComicAdmin::boot(); }
}
