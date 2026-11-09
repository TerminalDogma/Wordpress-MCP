<?php
namespace MSC;

/** Show-aware editorial navigation. UUID relationships remain authoritative. */
final class EpisodeAdmin {
	private const COLUMNS = array(
		'msc_parent_show'    => 'TV Show',
		'msc_season_number'  => 'Season',
		'msc_episode_number' => 'Episode',
		'msc_air_date'       => 'Air date',
	);
	public static function boot(): void {
		if ( ! isset( Schema::all()['msc_episode'], Schema::all()['msc_show'] ) ) {
			return;
		}
		EpisodeGroups::boot();
		add_filter( 'manage_msc_episode_posts_columns', array( self::class, 'columns' ), 20 );
		add_action( 'manage_msc_episode_posts_custom_column', array( self::class, 'column' ), 20, 2 );
		add_filter( 'manage_edit-msc_episode_sortable_columns', static fn( $columns ) => $columns + array_combine( array_keys( self::COLUMNS ), array_keys( self::COLUMNS ) ) );
		add_action(
			'restrict_manage_posts',
			static function ( $type, $which ) {
				if ( 'msc_episode' === $type && 'top' === $which ) {
					self::filters( false );
				}
			},
			20,
			2
		);
		add_action( 'pre_get_posts', array( self::class, 'prepare_query' ), 20 );
		add_filter( 'posts_clauses', array( self::class, 'clauses' ), 20, 2 );
		add_filter( 'the_title', array( self::class, 'title' ), 20, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 20, 2 );
		add_filter( 'admin_url', array( self::class, 'new_episode_url' ), 20, 3 );
		add_action( 'admin_notices', array( self::class, 'list_context' ) );
	}
	private static function input( string $name ): string {
		$value = $_GET[ $name ] ?? '';
		return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '!invalid';
	}
	public static function selection(): array {
		$parent       = self::input( 'msc_parent_show' );
		$season       = self::input( 'msc_season' );
		$valid_parent = '' === $parent || 'unassigned' === $parent || (bool) preg_match( '/^[a-f0-9-]{36}$/D', $parent );
		if ( $valid_parent && ! in_array( $parent, array( '', 'unassigned' ), true ) ) {
			$r            = Plugin::store()->get( $parent );
			$valid_parent = $r && 'msc_show' === $r['type'];
		}
		$valid_season = in_array( $season, array( '', 'unknown' ), true ) || (bool) preg_match( '/^(?:0|[1-9][0-9]{0,9})$/D', $season );
		return array(
			'parent' => $parent,
			'season' => $season,
			'valid'  => $valid_parent && $valid_season,
		);
	}
	public static function native_url( string $parent = '' ): string {
		return add_query_arg(
			array_filter(
				array(
					'post_type'       => 'msc_episode',
					'msc_parent_show' => $parent,
				),
				static fn( $v ) => '' !== $v
			),
			admin_url( 'edit.php' )
		);
	}
	public static function shared_url( string $parent = '' ): string {
		return add_query_arg(
			array_filter(
				array(
					'page'            => 'msc',
					'type'            => 'msc_episode',
					'msc_parent_show' => $parent,
				),
				static fn( $v ) => '' !== $v
			),
			admin_url( 'admin.php' )
		);
	}
	public static function add_url( string $parent ): string {
		return add_query_arg(
			array(
				'page'   => 'msc',
				'type'   => 'msc_episode',
				'new'    => 1,
				'parent' => $parent,
			),
			admin_url( 'admin.php' )
		);
	}
	public static function new_episode_url( string $url, string $path, $blog_id = null ): string {
		if ( null !== $blog_id && (int) $blog_id !== get_current_blog_id() || 'post-new.php?post_type=msc_episode' !== $path || ! function_exists( 'get_current_screen' ) || 'edit-msc_episode' !== ( get_current_screen()->id ?? '' ) ) {
			return $url;
		}
		$s = self::selection();
		if ( ! $s['valid'] || in_array( $s['parent'], array( '', 'unassigned' ), true ) ) {
			return $url;
		}
		$parent = self::parent( $s['parent'] );
		return $parent['record'] && self::can_add( $parent['record'] ) ? self::add_url( $s['parent'] ) : $url;
	}
	private static function can_add( array $show ): bool {
		$field = Schema::all()['msc_episode']['fields']['parent_show'] ?? array();
		return 'active' === $show['state'] && 'relationship' === ( $field['type'] ?? '' ) && 'msc_show' === ( $field['target'] ?? '' ) && current_user_can( 'msc_create_global' ) && Schema::enabled( 'msc_episode' ) && Schema::enabled( 'msc_show' );
	}
	private static function parent( string $uuid, bool $global = false ): array {
		$r = $uuid ? ( $global ? Plugin::store()->get( $uuid ) : Plugin::store()->resolved( $uuid ) ) : null;
		if ( ! $r || 'msc_show' !== $r['type'] ) {
			return array(
				'label'  => $uuid ? 'Unavailable TV Show' : 'No TV Show',
				'record' => null,
			);
		}
		$title = (string) ( $r['data']['title'] ?? '' );
		return array(
			'label'  => '' === $title ? '(Untitled TV Show)' : $title,
			'record' => $r,
		);
	}
	public static function position( array $data ): string {
		$season = $data['season_number'] ?? '';
		$number = $data['episode_number'] ?? '';
		if ( '' !== $season && null !== $season && '' !== $number && null !== $number ) {
			return 'S' . str_pad( (string) $season, 2, '0', STR_PAD_LEFT ) . 'E' . str_pad( (string) $number, 2, '0', STR_PAD_LEFT );
		}
		if ( '' !== $season && null !== $season ) {
			return 'Season ' . $season . ' · Unnumbered episode';
		}
		return '' !== $number && null !== $number ? 'Episode ' . $number . ' · Season not set' : 'Unnumbered episode';
	}
	private static function native_data( int $post ): array {
		return array(
			'parent_show'    => (string) get_post_meta( $post, 'msc_parent_show', true ),
			'season_number'  => get_post_meta( $post, 'msc_season_number', true ),
			'episode_number' => get_post_meta( $post, 'msc_episode_number', true ),
			'release_date'   => get_post_meta( $post, 'msc_release_date', true ),
		);
	}
	public static function title( string $title, int $post = 0 ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $post < 1 || ! is_admin() || ! $screen || 'edit' !== $screen->base || 'msc_episode' !== $screen->post_type || 'msc_episode' !== get_post_type( $post ) ) {
			return $title;
		}
		$data   = self::native_data( $post );
		$parent = self::parent( $data['parent_show'] );
		return $parent['label'] . ' — ' . self::position( $data ) . ' — ' . $title;
	}
	public static function columns( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result += self::COLUMNS;
			}
		}
		return $result;
	}
	public static function column( string $column, int $post ): void {
		if ( ! isset( self::COLUMNS[ $column ] ) ) {
			return;
		}
		$data = self::native_data( $post );
		if ( 'msc_parent_show' === $column ) {
			$parent = self::parent( $data['parent_show'] );
			if ( $parent['record'] ) {
				echo '<a href="' . esc_url( self::native_url( $data['parent_show'] ) ) . '">' . esc_html( $parent['label'] ) . '</a>';
			} else {
				echo esc_html( $parent['label'] );
			}
			return;
		}
		$value = $data[ array(
			'msc_season_number'  => 'season_number',
			'msc_episode_number' => 'episode_number',
			'msc_air_date'       => 'release_date',
		)[ $column ] ];
		echo '' === $value ? '—' : esc_html( (string) $value );
	}
	private static function shows( bool $global, string $selected ): array {
		global $wpdb;
		$records = Database::table( 'records' );
		$maps    = Database::table( 'maps' );
		$label   = "JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.title'))";
		$join    = '';
		if ( ! $global ) {
			$join  = $wpdb->prepare( " LEFT JOIN $maps m ON m.record_id=r.id AND m.site_id=%d", get_current_blog_id() ) . " LEFT JOIN $wpdb->posts p ON p.ID=m.post_id AND p.post_type='msc_show'";
			$label = "COALESCE(p.post_title,$label)";
		}
		$rows   = $wpdb->get_results( "SELECT r.uuid,$label AS label FROM $records r $join WHERE r.type='msc_show' AND r.state='active' ORDER BY label,r.id LIMIT 500", ARRAY_A );
		$result = array();
		foreach ( $rows as $row ) {
			$result[ $row['uuid'] ] = '' === (string) $row['label'] ? '(Untitled TV Show)' : $row['label'];
		}
		if ( $selected && 'unassigned' !== $selected && ! isset( $result[ $selected ] ) ) {
			$p = self::parent( $selected, $global );
			if ( $p['record'] ) {
				$result[ $selected ] = $p['label'];
			}
		}
		natcasesort( $result );
		return $result;
	}
	private static function seasons( bool $global, string $parent ): array {
		global $wpdb;
		if ( $global ) {
			$sql = 'SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(data,\'$.season_number\')) FROM ' . Database::table( 'records' ) . " WHERE type='msc_episode' AND state='active'";
			if ( $parent && 'unassigned' !== $parent ) {
				$sql .= $wpdb->prepare( " AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.parent_show'))=%s", $parent );
			}
		} else {
			$sql = "SELECT DISTINCT season.meta_value FROM $wpdb->posts p INNER JOIN $wpdb->postmeta season ON season.post_id=p.ID AND season.meta_key='msc_season_number' WHERE p.post_type='msc_episode' AND p.post_status NOT IN ('trash','auto-draft')";
			if ( $parent && 'unassigned' !== $parent ) {
				$sql .= $wpdb->prepare( " AND EXISTS (SELECT 1 FROM $wpdb->postmeta parent WHERE parent.post_id=p.ID AND parent.meta_key='msc_parent_show' AND parent.meta_value=%s)", $parent );
			}
		}
		$values = array_filter( $wpdb->get_col( $sql ), static fn( $v ) => is_scalar( $v ) && (bool) preg_match( '/^[0-9]{1,10}$/D', (string) $v ) );
		$values = array_values( array_unique( array_map( 'intval', $values ) ) );
		sort( $values, SORT_NUMERIC );
		return $values;
	}
	public static function filters( bool $global ): void {
		$s = self::selection();
		echo '<label class="screen-reader-text" for="msc-parent-show">Filter by TV Show</label><select id="msc-parent-show" name="msc_parent_show"><option value="">All TV Shows</option><option value="unassigned"' . selected( $s['parent'], 'unassigned', false ) . '>No TV Show</option>';
		foreach ( self::shows( $global, $s['parent'] ) as $uuid => $label ) {
			echo '<option value="' . esc_attr( $uuid ) . '"' . selected( $s['parent'], $uuid, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="msc-season">Filter by season</label><select id="msc-season" name="msc_season"><option value="">All seasons</option><option value="unknown"' . selected( $s['season'], 'unknown', false ) . '>Season not set</option>';
		$seasons = self::seasons( $global, $s['valid'] ? $s['parent'] : '' );
		if ( preg_match( '/^[0-9]{1,10}$/D', $s['season'] ) && ! in_array( (int) $s['season'], $seasons, true ) ) {
			$seasons[] = (int) $s['season'];
			sort( $seasons, SORT_NUMERIC );
		}
		foreach ( $seasons as $season ) {
			echo '<option value="' . $season . '"' . selected( $s['season'], (string) $season, false ) . '>Season ' . $season . '</option>';
		}
		echo '</select> ';
	}
	public static function prepare_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || 'msc_episode' !== $query->get( 'post_type' ) ) {
			return;
		}
		$s = self::selection();
		if ( ! $s['valid'] ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$filters = array();
		if ( 'unassigned' === $s['parent'] ) {
			$filters[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'msc_parent_show',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => 'msc_parent_show',
					'value' => '',
				),
			);
		} elseif ( '' !== $s['parent'] ) {
			$filters[] = array(
				'key'   => 'msc_parent_show',
				'value' => $s['parent'],
			);
		}
		if ( 'unknown' === $s['season'] ) {
			$filters[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'msc_season_number',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => 'msc_season_number',
					'value' => '',
				),
			);
		} elseif ( '' !== $s['season'] ) {
			$filters[] = array(
				'key'   => 'msc_season_number',
				'value' => $s['season'],
			);
		}
		if ( $filters ) {
			$existing = $query->get( 'meta_query' );
			if ( $existing ) {
				$filters[] = $existing;
			}
			$query->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $filters ) );
		}
		$orderby = $query->get( 'orderby' );
		if ( ! $orderby || isset( self::COLUMNS[ is_string( $orderby ) ? $orderby : '' ] ) ) {
			$query->set( '_msc_episode_sort', $orderby ?: 'msc_parent_show' );
			$query->set( '_msc_episode_direction', $orderby && 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ? 'DESC' : 'ASC' );
		}
	}
	private static function numeric_sql( string $expression ): string {
		return "CASE WHEN $expression REGEXP '^[0-9]+$' THEN CAST($expression AS UNSIGNED) ELSE NULL END";
	}
	public static function clauses( array $clauses, \WP_Query $query ): array {
		$sort = $query->get( '_msc_episode_sort' );
		if ( ! is_admin() || ! $query->is_main_query() || 'msc_episode' !== $query->get( 'post_type' ) || ! isset( self::COLUMNS[ is_string( $sort ) ? $sort : '' ] ) ) {
			return $clauses;
		}
		global $wpdb;
		$records          = Database::table( 'records' );
		$maps             = Database::table( 'maps' );
		$clauses['join'] .= " LEFT JOIN $wpdb->postmeta msc_ep_parent ON msc_ep_parent.post_id=$wpdb->posts.ID AND msc_ep_parent.meta_key='msc_parent_show' LEFT JOIN $records msc_ep_show ON msc_ep_show.uuid=msc_ep_parent.meta_value AND msc_ep_show.type='msc_show'";
		$clauses['join'] .= $wpdb->prepare( " LEFT JOIN $maps msc_ep_show_map ON msc_ep_show_map.record_id=msc_ep_show.id AND msc_ep_show_map.site_id=%d", get_current_blog_id() );
		$clauses['join'] .= " LEFT JOIN $wpdb->posts msc_ep_show_post ON msc_ep_show_post.ID=msc_ep_show_map.post_id AND msc_ep_show_post.post_type='msc_show'";
		foreach ( array(
			'season' => 'msc_season_number',
			'number' => 'msc_episode_number',
			'air'    => 'msc_release_date',
		) as $alias => $key ) {
			$clauses['join'] .= " LEFT JOIN $wpdb->postmeta msc_ep_$alias ON msc_ep_$alias.post_id=$wpdb->posts.ID AND msc_ep_$alias.meta_key='$key'";
		}
		$show               = "COALESCE(msc_ep_show_post.post_title,JSON_UNQUOTE(JSON_EXTRACT(msc_ep_show.data,'$.title')),'')";
		$season             = self::numeric_sql( 'msc_ep_season.meta_value' );
		$number             = self::numeric_sql( 'msc_ep_number.meta_value' );
		$date               = "NULLIF(msc_ep_air.meta_value,'')";
		$direction          = 'DESC' === $query->get( '_msc_episode_direction' ) ? 'DESC' : 'ASC';
		$primary            = array(
			'msc_parent_show'    => $show,
			'msc_season_number'  => $season,
			'msc_episode_number' => $number,
			'msc_air_date'       => $date,
		)[ $sort ];
		$empty              = 'msc_parent_show' === $sort ? "$show='' ASC, " : '';
		$clauses['orderby'] = "$primary IS NULL ASC, $empty$primary $direction, $show='' ASC, $show ASC, msc_ep_parent.meta_value ASC, $season IS NULL ASC, $season ASC, $number IS NULL ASC, $number ASC, $wpdb->posts.post_title ASC, $wpdb->posts.ID ASC";
		$clauses['groupby'] = $clauses['groupby'] ? $clauses['groupby'] . ", $wpdb->posts.ID" : "$wpdb->posts.ID";
		return $clauses;
	}
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( ! in_array( $post->post_type, array( 'msc_show', 'msc_episode' ), true ) || ! current_user_can( 'edit_posts' ) || ! Schema::enabled( 'msc_episode' ) ) {
			return $actions;
		}
		$r = Plugin::store()->by_post( $post->ID );
		if ( ! $r ) {
			return $actions;
		}
		if ( 'msc_show' === $post->post_type ) {
			$actions['msc_episodes'] = '<a href="' . esc_url( self::native_url( $r['uuid'] ) ) . '">Episodes</a>';
			if ( self::can_add( $r ) ) {
				$actions['msc_add_episode'] = '<a href="' . esc_url( self::add_url( $r['uuid'] ) ) . '">Add Episode</a>';
			}
		} elseif ( 'msc_episode' === $post->post_type ) {
			$data   = self::native_data( $post->ID );
			$parent = self::parent( $data['parent_show'] );
			if ( $parent['record'] && Schema::enabled( 'msc_show' ) ) {
				$actions['msc_parent_show'] = '<a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $parent['record']['uuid'] ) ) . '">TV Show</a>';
				$actions['msc_siblings']    = '<a href="' . esc_url( self::native_url( $data['parent_show'] ) ) . '">Other Episodes</a>';
			}
		}
		return $actions;
	}
	public static function context( array $record, bool $global = false ): void {
		if ( ! Schema::enabled( 'msc_episode' ) ) {
			return;
		}
		if ( 'msc_show' === $record['type'] ) {
			echo '<p class="msc-episode-navigation"><a class="button" href="' . esc_url( $global ? self::shared_url( $record['uuid'] ) : self::native_url( $record['uuid'] ) ) . '">View Episodes</a> ';
			if ( self::can_add( $record ) ) {
				echo '<a class="button" href="' . esc_url( self::add_url( $record['uuid'] ) ) . '">Add Episode</a>';
			}
			echo '</p>';
		} elseif ( 'msc_episode' === $record['type'] ) {
			$data   = $global ? ( $record['global'] ?? $record['data'] ) : $record['data'];
			$parent = self::parent( (string) ( $data['parent_show'] ?? '' ), $global );
			echo '<div class="notice notice-info inline msc-episode-context"><p><strong>' . ( $global ? 'Global TV Show: ' : 'TV Show: ' ) . esc_html( $parent['label'] ) . '</strong> · ' . esc_html( self::position( $data ) );
			if ( $parent['record'] && Schema::enabled( 'msc_show' ) ) {
				echo ' · <a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $parent['record']['uuid'] . ( $global ? '&mode=global' : '' ) ) ) . '">Manage TV Show</a> · <a href="' . esc_url( $global ? self::shared_url( $parent['record']['uuid'] ) : self::native_url( $parent['record']['uuid'] ) ) . '">View Episodes</a>';
			}
			echo '</p></div>';
		}
	}
	public static function new_context( string $type ): void {
		$uuid = $_GET['parent'] ?? '';
		if ( 'msc_episode' !== $type || ! is_string( $uuid ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $uuid ) ) {
			return;
		}
		$parent = self::parent( $uuid, true );
		if ( $parent['record'] ) {
			self::context(
				array(
					'type' => 'msc_episode',
					'data' => array( 'parent_show' => $uuid ),
				),
				true
			);
		}
	}
	public static function list_context(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-msc_episode' !== $screen->id || ! Schema::enabled( 'msc_show' ) ) {
			return;
		}
		$s = self::selection();
		if ( '' === $s['parent'] ) {
			echo '<div class="notice notice-info"><p><a href="' . esc_url( EpisodeGroups::url() ) . '">Browse Episodes by TV Show and season</a>.</p></div>';
			return;
		}
		if ( ! $s['valid'] ) {
			echo '<div class="notice notice-error"><p>The TV Show or season filter is invalid. <a href="' . esc_url( self::native_url() ) . '">View all Episodes</a>.</p></div>';
			return;
		}
		$parent = self::parent( 'unassigned' === $s['parent'] ? '' : $s['parent'] );
		echo '<div class="notice notice-info"><p><strong>Episodes: ' . esc_html( $parent['label'] ) . '</strong> · <a href="' . esc_url( EpisodeGroups::url() ) . '">Browse TV Shows</a> · <a href="' . esc_url( EpisodeGroups::url( $s['parent'] ) ) . '">Seasons</a> · <a href="' . esc_url( self::native_url() ) . '">All Episodes</a>';
		if ( $parent['record'] && self::can_add( $parent['record'] ) ) {
			echo ' · <a href="' . esc_url( self::add_url( $parent['record']['uuid'] ) ) . '">Add Episode to this TV Show</a>';
		}
		echo '</p></div>';
	}
	/** Global catalog scope deliberately includes active draft/unpublished records. */
	public static function shared_rows( array $selection, string $search = '', int $page = 1 ): array {
		if ( ! $selection['valid'] ) {
			return array(
				'records' => array(),
				'total'   => 0,
			);
		}
		global $wpdb;
		$table  = Database::table( 'records' );
		$parent = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.parent_show')),'')";
		$season = "JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.season_number'))";
		$number = "JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.episode_number'))";
		$where  = "r.type='msc_episode' AND r.state='active'";
		if ( 'unassigned' === $selection['parent'] ) {
			$where .= " AND $parent=''";
		} elseif ( '' !== $selection['parent'] ) {
			$where .= $wpdb->prepare( " AND $parent=%s", $selection['parent'] );
		}
		if ( 'unknown' === $selection['season'] ) {
			$where .= " AND COALESCE($season,'')=''";
		} elseif ( '' !== $selection['season'] ) {
			$where .= $wpdb->prepare( " AND $season=%s", $selection['season'] );
		}
		$show_title = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(s.data,'$.title')),'')";
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( " AND (JSON_UNQUOTE(JSON_EXTRACT(r.data,'$.title')) LIKE %s OR $show_title LIKE %s)", $like, $like );
		}
		$from   = "$table r LEFT JOIN $table s ON s.uuid=$parent AND s.type='msc_show'";
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $from WHERE $where" );
		$season = self::numeric_sql( $season );
		$number = self::numeric_sql( $number );
		$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT r.id FROM $from WHERE $where ORDER BY $show_title='' ASC,$show_title ASC,$parent ASC,$season IS NULL ASC,$season ASC,$number IS NULL ASC,$number ASC,r.id ASC LIMIT 50 OFFSET %d", ( max( 1, min( 1000000, $page ) ) - 1 ) * 50 ) );
		return array(
			'records' => array_map( static fn( $id ) => Plugin::store()->get( (int) $id ), $ids ),
			'total'   => $total,
		);
	}
	public static function shared_list(): void {
		$s      = self::selection();
		$search = mb_substr( self::input( 'msc_search' ), 0, 200 );
		$page   = max( 1, absint( self::input( 'msc_paged' ) ) );
		$parent = $s['valid'] && ! in_array( $s['parent'], array( '', 'unassigned' ), true ) ? self::parent( $s['parent'], true ) : null;
		echo '<h2>Episodes' . ( $parent ? ' — ' . esc_html( $parent['label'] ) : '' ) . '</h2>';
		if ( current_user_can( 'msc_create_global' ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( $parent && $parent['record'] && self::can_add( $parent['record'] ) ? self::add_url( $s['parent'] ) : admin_url( 'admin.php?page=msc&type=msc_episode&new=1' ) ) . '">Create global Episode</a></p>';
		}
		$local_url = add_query_arg( 'msc_season', $s['valid'] ? $s['season'] : '', $s['valid'] ? self::native_url( $s['parent'] ) : self::native_url() );
		echo '<p>Shared Episodes are ordered by TV Show, season and episode number. This global catalog includes unpublished records. <a href="' . esc_url( $local_url ) . '">Open this site’s Episode list</a> for local publishing and site-specific relationships.</p><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="msc-episode-filters"><input type="hidden" name="page" value="msc"><input type="hidden" name="type" value="msc_episode">';
		self::filters( true );
		echo '<label class="screen-reader-text" for="msc-episode-search">Search Episodes or TV Shows</label><input id="msc-episode-search" type="search" name="msc_search" value="' . esc_attr( $search ) . '" placeholder="Episode or TV Show title"> <button class="button">Filter Episodes</button></form>';
		if ( ! $s['valid'] ) {
			echo '<p>The TV Show or season filter is invalid.</p>';
		}
		$result = self::shared_rows( $s, $search, $page );
		echo '<table class="widefat striped msc-episode-catalog"><thead><tr><th>Episode title</th><th>TV Show</th><th>Season</th><th>Episode</th><th>Air date</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $result['records'] as $r ) {
			$data   = $r['data'];
			$parent = self::parent( (string) ( $data['parent_show'] ?? '' ), true );
			$edit   = admin_url( 'admin.php?page=msc&record=' . $r['uuid'] );
			echo '<tr><td><a href="' . esc_url( $edit ) . '"><strong>' . esc_html( self::position( $data ) . ' — ' . ( $data['title'] ?? '(Untitled)' ) ) . '</strong></a></td><td>';
			if ( $parent['record'] ) {
				echo '<a href="' . esc_url( self::shared_url( $parent['record']['uuid'] ) ) . '">' . esc_html( $parent['label'] ) . '</a>';
			} else {
				echo esc_html( $parent['label'] );
			}
			echo '</td><td>' . esc_html( (string) ( $data['season_number'] ?? '—' ) ) . '</td><td>' . esc_html( (string) ( $data['episode_number'] ?? '—' ) ) . '</td><td>' . esc_html( $data['release_date'] ?? '—' ) . '</td><td><a href="' . esc_url( $edit ) . '">Manage fields</a></td></tr>';
		}
		if ( ! $result['records'] ) {
			echo '<tr><td colspan="6">No Episodes match these filters.</td></tr>';
		}
		echo '</tbody></table>';
		$pages = max( 1, (int) ceil( $result['total'] / 50 ) );
		echo '<p>' . esc_html( $result['total'] . ' Episodes · Page ' . $page . ' of ' . $pages ) . '</p>';
		foreach ( array(
			$page - 1 => 'Previous page',
			$page + 1 => 'Next page',
		) as $number => $label ) {
			if ( $number >= 1 && $number <= $pages ) {
				echo '<a class="button" href="' . esc_url(
					add_query_arg(
						array(
							'msc_paged'  => $number,
							'msc_season' => $s['season'],
							'msc_search' => $search,
						),
						self::shared_url( $s['parent'] )
					)
				) . '">' . esc_html( $label ) . '</a> ';
			}
		}
	}
}
