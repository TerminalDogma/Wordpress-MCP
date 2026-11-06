<?php
namespace MSC;

/** Browse local Episodes in Show and season batches before opening a post list. */
final class EpisodeGroups {
    public static function boot(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ) );
        add_filter( 'parent_file', static function ( $parent ) { return Schema::enabled( 'msc_show' ) && Schema::enabled( 'msc_episode' ) && 'msc_episode' === ( get_current_screen()->post_type ?? '' ) ? 'msc-episodes' : $parent; } );
    }
    public static function menu(): void {
        if ( ! Schema::enabled( 'msc_episode' ) || ! Schema::enabled( 'msc_show' ) ) { return; }
        add_menu_page( 'Episodes by TV Show', 'Episodes', 'edit_posts', 'msc-episodes', array( self::class, 'page' ), 'dashicons-playlist-video', 22 );
        add_submenu_page( 'msc-episodes', 'Episodes by TV Show', 'By TV Show', 'edit_posts', 'msc-episodes', array( self::class, 'page' ), 0 );
    }
    public static function url( string $parent = '' ): string {
        return add_query_arg( array_filter( array( 'page' => 'msc-episodes', 'msc_parent_show' => $parent ), static fn( $v ) => '' !== $v ), admin_url( 'admin.php' ) );
    }
    private static function visibility(): string {
        global $wpdb;
        $where = "e.post_type='msc_episode' AND e.post_status NOT IN ('trash','auto-draft','inherit')";
        if ( ! current_user_can( 'edit_others_posts' ) ) { $where .= $wpdb->prepare( ' AND e.post_author=%d', get_current_user_id() ); }
        if ( ! current_user_can( 'read_private_posts' ) ) { $where .= $wpdb->prepare( " AND (e.post_status<>'private' OR e.post_author=%d)", get_current_user_id() ); }
        return $where;
    }
    private static function from(): string {
        global $wpdb;
        return "$wpdb->posts e LEFT JOIN $wpdb->postmeta parent ON parent.post_id=e.ID AND parent.meta_key='msc_parent_show' LEFT JOIN $wpdb->postmeta season ON season.post_id=e.ID AND season.meta_key='msc_season_number'";
    }
    private static function season_sql(): string { return "CASE WHEN season.meta_value REGEXP '^(0|[1-9][0-9]{0,9})$' THEN season.meta_value ELSE 'unknown' END"; }
    public static function groups( string $search = '', int $page = 1 ): array {
        if ( ! current_user_can( 'edit_posts' ) || ! Schema::enabled( 'msc_episode' ) || ! Schema::enabled( 'msc_show' ) ) { return array( 'rows' => array(), 'total' => 0 ); }
        global $wpdb; $records = Database::table( 'records' ); $maps = Database::table( 'maps' );
        $parent = "COALESCE(parent.meta_value,'')";
        $title = "CASE WHEN $parent='' THEN 'No TV Show' WHEN r.id IS NULL THEN 'Unavailable TV Show' ELSE COALESCE(NULLIF(sh.post_title,''),NULLIF(JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.title')),''),'(Untitled TV Show)') END";
        $from = self::from() . " LEFT JOIN $records r ON r.uuid=parent.meta_value AND r.type='msc_show'" . $wpdb->prepare( " LEFT JOIN $maps m ON m.record_id=r.id AND m.site_id=%d", get_current_blog_id() ) . " LEFT JOIN $wpdb->posts sh ON sh.ID=m.post_id AND sh.post_type='msc_show'";
        $where = self::visibility(); if ( '' !== $search ) { $where .= $wpdb->prepare( " AND $title LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ); }
        $grouped = "SELECT $parent AS parent,MAX($title) AS title,MAX(r.id) AS record_id,COUNT(DISTINCT e.ID) AS episodes,COUNT(DISTINCT " . self::season_sql() . ") AS seasons FROM $from WHERE $where GROUP BY $parent";
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ($grouped) episode_groups" );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ($grouped) episode_groups ORDER BY parent='' ASC,title ASC,parent ASC LIMIT 25 OFFSET %d", ( max( 1, min( 1000000, $page ) ) - 1 ) * 25 ), ARRAY_A );
        return array( 'rows' => $rows, 'total' => $total );
    }
    public static function seasons( string $parent ): array {
        if ( ! current_user_can( 'edit_posts' ) || ! Schema::enabled( 'msc_episode' ) || ! Schema::enabled( 'msc_show' ) ) { return array(); }
        global $wpdb; $selection = 'unassigned' === $parent ? '' : $parent;
        $where = self::visibility() . $wpdb->prepare( " AND COALESCE(parent.meta_value,'')=%s", $selection ); $season = self::season_sql();
        return $wpdb->get_results( 'SELECT ' . $season . ' AS season,COUNT(DISTINCT e.ID) AS episodes FROM ' . self::from() . " WHERE $where GROUP BY $season ORDER BY season='unknown' ASC,CAST(season AS UNSIGNED) ASC", ARRAY_A );
    }
    public static function page(): void {
        if ( ! current_user_can( 'edit_posts' ) || ! Schema::enabled( 'msc_episode' ) || ! Schema::enabled( 'msc_show' ) ) { wp_die( 'Episode administration is not available for this site.' ); }
        $selection = EpisodeAdmin::selection(); $parent = $selection['parent'];
        echo '<div class="wrap msc msc-episode-groups"><h1>Episodes by TV Show</h1>';
        if ( '' !== $parent ) {
            echo '<p><a href="' . esc_url( self::url() ) . '">← All TV Shows</a></p>';
            if ( ! $selection['valid'] ) { echo '<p>The TV Show filter is invalid.</p></div>'; return; }
            $record = 'unassigned' === $parent ? null : Plugin::store()->resolved( $parent );
            echo '<h2>' . esc_html( $record ? ( $record['data']['title'] ?: '(Untitled TV Show)' ) : 'No TV Show' ) . '</h2>';
            if ( $record ) { EpisodeAdmin::context( $record ); }
            else { echo '<p><a class="button" href="' . esc_url( EpisodeAdmin::native_url( 'unassigned' ) ) . '">View unassigned Episodes</a></p>'; }
            echo '<p>Select a season to edit, publish or update that batch of Episodes. Counts include drafts and use this site’s parent and season overrides.</p><table class="widefat striped msc-season-groups"><thead><tr><th>Season</th><th>Episodes</th><th>Actions</th></tr></thead><tbody>';
            $rows = self::seasons( $parent );
            foreach ( $rows as $row ) {
                $label = 'unknown' === $row['season'] ? 'Season not set' : 'Season ' . $row['season']; $url = add_query_arg( 'msc_season', $row['season'], EpisodeAdmin::native_url( $parent ) );
                echo '<tr data-season="' . esc_attr( $row['season'] ) . '"><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $label ) . '</strong></a></td><td>' . esc_html( $row['episodes'] ) . '</td><td><a class="button" href="' . esc_url( $url ) . '">Manage Episodes</a></td></tr>';
            }
            if ( ! $rows ) { echo '<tr><td colspan="3">No Episodes for this TV Show on this site.</td></tr>'; }
            echo '</tbody></table></div>'; return;
        }
        $search = isset( $_GET['msc_search'] ) && is_string( $_GET['msc_search'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['msc_search'] ) ), 0, 200 ) : '';
        $page = isset( $_GET['msc_paged'] ) && is_scalar( $_GET['msc_paged'] ) ? max( 1, absint( $_GET['msc_paged'] ) ) : 1; $result = self::groups( $search, $page );
        echo '<p>Select a TV Show, then a season, to manage its Episodes as a batch. Only Shows with Episodes on this site appear here. <a href="' . esc_url( EpisodeAdmin::native_url() ) . '">All Episodes</a> opens the complete post list.</p><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="msc-episode-filters"><input type="hidden" name="page" value="msc-episodes"><label class="screen-reader-text" for="msc-show-search">Search TV Shows</label><input type="search" id="msc-show-search" name="msc_search" placeholder="TV Show title" value="' . esc_attr( $search ) . '"><button class="button">Search TV Shows</button></form>';
        echo '<table class="widefat striped msc-show-groups"><thead><tr><th>TV Show</th><th>Seasons</th><th>Episodes</th><th>Actions</th></tr></thead><tbody>';
        foreach ( $result['rows'] as $row ) {
            $uuid = $row['parent'] ?: 'unassigned'; $valid = 'unassigned' === $uuid || $row['record_id']; $url = $valid ? self::url( $uuid ) : EpisodeAdmin::native_url();
            echo '<tr data-parent="' . esc_attr( $uuid ) . '"><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $row['title'] ) . '</strong></a></td><td>' . esc_html( $row['seasons'] ) . '</td><td>' . esc_html( $row['episodes'] ) . '</td><td><a class="button" href="' . esc_url( $url ) . '">' . ( $valid ? 'View seasons' : 'All Episodes' ) . '</a></td></tr>';
        }
        if ( ! $result['rows'] ) { echo '<tr><td colspan="4">No TV Shows with Episodes match this search. Create a TV Show and use its Add Episode action to begin.</td></tr>'; }
        echo '</tbody></table>'; $pages = max( 1, (int) ceil( $result['total'] / 25 ) );
        echo '<p>' . esc_html( $result['total'] . ' TV Show groups · Page ' . $page . ' of ' . $pages ) . '</p>';
        foreach ( array( $page - 1 => 'Previous page', $page + 1 => 'Next page' ) as $number => $label ) { if ( $number >= 1 && $number <= $pages ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'msc_paged' => $number, 'msc_search' => $search ), self::url() ) ) . '">' . esc_html( $label ) . '</a> '; } }
        echo '</div>';
    }
}
