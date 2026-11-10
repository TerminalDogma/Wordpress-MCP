<?php
namespace MSC;
final class Plugin {
	private static ?Store $store = null;
	private static ?Materializer $materializer = null;
	public static function store(): Store { return self::$store ??= new Store(); }
	public static function materializer(): Materializer { return self::$materializer ??= new Materializer(); }
	public static function activate( bool $network ): void {
		if ( ! is_multisite() || ! $network ) { wp_die( 'Multisite Shared Content requires WordPress Multisite network activation.' ); }
		Database::migrate();
		if ( ! get_network_option( get_current_network_id(), 'msc_schemas' ) ) { update_network_option( get_current_network_id(), 'msc_schemas', Schema::defaults() ); }
		Permissions::install_roles();
		self::schedule();
	}
	public static function deactivate(): void { wp_clear_scheduled_hook( 'msc_tick' ); }
	private static function schedule(): void {
		// One cron per network, on its main site; a system cron or WP-CLI is recommended.
		$main = (int) get_main_site_id();
		switch_to_blog( $main );
		try { if ( ! wp_next_scheduled( 'msc_tick' ) ) { wp_schedule_event( time() + 60, 'msc_minute', 'msc_tick' ); } }
		finally { restore_current_blog(); }
	}
	public static function boot(): void {
		if ( ! is_multisite() ) { return; }
		Schema::upgrade_defaults();
		GameFields::boot(); Comics::boot();
		MediaImport::boot();
		ImageSearch::boot();
		Trailers::boot();
		Reviews::boot();
		ScoreImport::boot();
		CatalogArchive::boot();
		add_filter( 'cron_schedules', static function ( $s ) { $s['msc_minute'] = array( 'interval' => 60, 'display' => 'Every minute (MSC)' ); return $s; } );
		add_action( 'init', static function () { if ( 1 !== (int) get_network_option( get_current_network_id(), 'msc_db_version' ) ) { Database::migrate(); } self::register_posts(); }, 8 );
		add_action( 'init', static function () {
			Permissions::ensure_roles();
			if ( get_option( 'msc_flush_rewrite' ) ) { flush_rewrite_rules( false ); delete_option( 'msc_flush_rewrite' ); }
			if ( (int) get_current_blog_id() === (int) get_main_site_id() ) { self::schedule(); }
			self::block();
		}, 20 );
		add_action( 'msc_tick', static function () { Queue::run( 100 ); } );
		add_action( 'wp_initialize_site', static function ( $site ) { switch_to_blog( (int) $site->blog_id ); try { Permissions::install_roles(); } finally { restore_current_blog(); } }, 20 );
		add_action( 'wp_after_insert_post', array( Materializer::class, 'capture' ), 20, 2 );
		add_action( 'deleted_post', array( Materializer::class, 'deleted' ) );
		add_action( 'trashed_post', array( Materializer::class, 'trashed' ) );
		add_action( 'untrashed_post', static function ( $id ) { $r = self::store()->by_post( $id ); if ( $r ) { Queue::sync( (int) $r['id'], get_current_blog_id() ); } } );
		add_action( 'rest_api_init', array( Rest::class, 'routes' ) );
		add_action( 'rest_api_init', static function () {
			foreach ( Schema::all() as $type => $schema ) {
				register_rest_field( $type, 'msc_state', array( 'get_callback' => static function ( $post ) { $r = self::store()->by_post( (int) $post['id'] ); return $r ? array( 'uuid' => $r['uuid'], 'local_version' => (int) self::store()->mapping( (int) $r['id'] )['local_version'] ) : null; }, 'schema' => array( 'type' => array( 'object', 'null' ), 'context' => array( 'edit' ), 'readonly' => true ) ) );
			}
		} );
		foreach ( Schema::all() as $type => $schema ) {
			add_filter( 'rest_pre_insert_' . $type, array( self::class, 'rest_guard' ), 10, 2 );
		}
		add_filter( 'wp_insert_post_data', array( self::class, 'native_guard' ), 10, 2 );
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $hook ) {
			add_filter( $hook, static function ( $check, $post_id, $key ) {
				if ( ! Materializer::$writing && isset( Schema::all()[ get_post_type( $post_id ) ?: '' ] ) && ( str_starts_with( $key, 'msc_' ) || str_starts_with( $key, '_msc_' ) || '_thumbnail_id' === $key ) ) { return false; }
				return $check;
			}, 10, 3 );
		}

		add_action( 'enqueue_block_editor_assets', static function () {
			$post_id = get_the_ID(); $r = $post_id ? self::store()->by_post( $post_id ) : null;
			wp_enqueue_script( 'msc-editor', plugins_url( 'assets/editor.js', MSC_FILE ), array( 'wp-api-fetch', 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks' ), MSC_VERSION, true );
			wp_add_inline_script( 'msc-editor', 'window.mscEditor=' . wp_json_encode( array( 'types' => array_keys( Schema::all() ), 'version' => $r ? (int) self::store()->mapping( (int) $r['id'] )['local_version'] : null, 'shared' => isset( Schema::all()[ get_current_screen()->post_type ?? '' ] ) ) ) . ';', 'before' );
		} );
		add_filter( 'post_thumbnail_html', static function ( $html, $post_id ) {
			$r = self::store()->by_post( (int) $post_id );
			if ( ! $r ) { return $html; } $data = self::store()->resolved( (int) $r['id'] )['data']; $url = Media::url( (array) ( $data['featured_image'] ?? array() ) );
			return $url ? '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $data['title'] ?? '' ) . '" loading="lazy">' : '';
		}, 10, 2 );
		add_filter( 'has_post_thumbnail', static function ( $has, $post ) { $post = get_post( $post ); if ( ! $post ) { return $has; } $r = self::store()->by_post( (int) $post->ID ); return $r ? (bool) Media::url( (array) ( self::store()->resolved( $r['id'] )['data']['featured_image'] ?? array() ) ) : $has; }, 10, 2 );
		Seo::boot(); Admin::boot(); Acf::boot(); Presentation::boot(); OpenAdmin::boot(); EpisodeAdmin::boot(); PersonReview::boot(); Propagation::boot(); GamePolicy::boot(); NetworkSites::boot(); IgdbAdmin::boot(); ReleaseCalendar::boot(); UpcomingReleases::boot(); if ( defined( 'MSC_ENABLE_LEGACY_OMDB' ) && MSC_ENABLE_LEGACY_OMDB ) { OmdbAdmin::boot(); }
		if ( defined( 'WP_CLI' ) && WP_CLI ) { \WP_CLI::add_command( 'msc', CLI::class ); }
		do_action( 'msc_register_field_types' );
	}
	public static function register_posts(): void {
		foreach ( Schema::all() as $type => $schema ) {
			if ( ! Schema::enabled( $type ) || post_type_exists( $type ) ) { continue; }
			register_post_type( $type, array(
				'label' => $schema['label'], 'labels' => array_filter( array( 'singular_name' => array( 'msc_person' => 'Person', 'msc_movie' => 'Movie', 'msc_show' => 'TV Show', 'msc_episode' => 'Episode', 'msc_game' => 'Video Game' )[ $type ] ?? ( EntityCatalog::definitions()[ $type ][1] ?? ( Comics::singular( $type ) ?: $schema['label'] ) ), 'all_items' => 'msc_episode' === $type ? 'All Episodes' : null ) ), 'public' => true, 'show_in_rest' => true,
				'show_in_menu' => in_array( $type, array( 'msc_comic_issue', 'msc_comic_arc' ), true ) && Schema::enabled( 'msc_comic' ) ? 'edit.php?post_type=msc_comic' : ( 'msc_episode' === $type && Schema::enabled( 'msc_show' ) ? ( get_option( 'msc_episodes_as_children' ) ? 'edit.php?post_type=msc_show' : 'msc-episodes' ) : true ),
				'has_archive' => (bool) ( $schema['archive'] ?? true ), 'rewrite' => array( 'slug' => $schema['slug'] ),
				'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
				'capability_type' => 'post', 'map_meta_cap' => true,
				'capabilities' => array( 'create_posts' => 'msc_create_global', 'publish_posts' => 'msc_publish_local' ),
				'menu_icon' => array( 'msc_person' => 'dashicons-admin-users', 'msc_movie' => 'dashicons-video-alt2', 'msc_show' => 'dashicons-desktop', 'msc_episode' => 'dashicons-playlist-video', 'msc_game' => 'dashicons-games', 'msc_comic' => 'dashicons-book', 'msc_comic_issue' => 'dashicons-media-document', 'msc_comic_arc' => 'dashicons-editor-ol' )[ $type ] ?? 'dashicons-networking',
			) );
		}
		Taxonomies::register();
	}
	private static function changing( array $data, \WP_Post $old, array $schema ): array {
		$changed = array();
		foreach ( $schema['fields'] as $key => $field ) { if ( isset( $field['property'] ) && isset( $data[ $field['property'] ] ) && (string) wp_unslash( $data[ $field['property'] ] ) !== (string) $old->{$field['property']} ) { $changed[] = $key; } }
		return $changed;
	}
	public static function rest_guard( $prepared, $request ) {
		if ( is_wp_error( $prepared ) ) { return $prepared; }
		$id = (int) $request['id']; $r = $id ? self::store()->by_post( $id ) : null;
		if ( ! $r ) { return current_user_can( 'msc_create_global' ) ? $prepared : new \WP_Error( 'msc_forbidden', 'Create Global permission required.', array( 'status' => 403 ) ); }
		$m = self::store()->mapping( (int) $r['id'] );
		if ( (int) $request['msc_expected_version'] !== (int) $m['local_version'] ) { return new \WP_Error( 'msc_conflict', 'Local record changed. Reload before saving.', array( 'status' => 409 ) ); }
		$old = get_post( $id );
		foreach ( self::changing( (array) $prepared, $old, Schema::get( $r['type'] ) ) as $key ) {
			if ( ! current_user_can( 'msc_override_local' ) || false === ( Schema::get( $r['type'] )['fields'][ $key ]['override'] ?? true ) ) { return new \WP_Error( 'msc_locked', 'Field is network locked or permission is missing.', array( 'status' => 403 ) ); }
		}
		if ( isset( $request['featured_media'] ) && (int) $request['featured_media'] !== (int) get_post_thumbnail_id( $id ) ) { return new \WP_Error( 'msc_media', 'Use Shared Content → Manage fields to change media with an explicit site/attachment reference.', array( 'status' => 400 ) ); }
		if ( ! self::claim_native( $id, (int) $m['local_version'] ) ) { return new \WP_Error( 'msc_conflict', 'Local record changed. Reload before saving.', array( 'status' => 409 ) ); }
		return $prepared;
	}
	private static function claim_native( int $post, int $expected ): bool {
		global $wpdb; $r = self::store()->by_post( $post );
		if ( ! $r ) { return false; }
		$changed = Database::check( $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::table( 'maps' ) . ' SET local_version=local_version+1 WHERE record_id=%d AND site_id=%d AND local_version=%d', $r['id'], get_current_blog_id(), $expected ) ) );
		if ( 1 === $changed ) { Materializer::$native_versions[ $post ] = $expected + 1; return true; }
		return false;
	}
	public static function native_guard( array $data, array $input ): array {
		if ( Materializer::$writing || ! isset( Schema::all()[ $data['post_type'] ] ) || empty( $input['ID'] ) ) { return $data; }
		$r = self::store()->by_post( (int) $input['ID'] ); if ( ! $r ) { return $data; }
		$old = get_post( (int) $input['ID'] ); $schema = Schema::get( $r['type'] );
		$expected = isset( $_POST['msc_expected_version'] ) ? (int) $_POST['msc_expected_version'] : (int) self::store()->mapping( (int) $r['id'] )['local_version'];
		$conflict = ! isset( Materializer::$native_versions[ (int) $input['ID'] ] ) && ! self::claim_native( (int) $input['ID'], $expected );
		foreach ( self::changing( $data, $old, $schema ) as $key ) {
			if ( $conflict || ! current_user_can( 'msc_override_local' ) || false === ( $schema['fields'][ $key ]['override'] ?? true ) ) {
				$data[ $schema['fields'][ $key ]['property'] ] = wp_slash( $old->{$schema['fields'][ $key ]['property']} );
				Admin::notice( $conflict ? 'Conflict: local post changed. Reload before saving.' : 'A field is network locked or local override permission is missing.', 'error' );
			}
		}
		if ( 'active' !== $r['state'] || ! Schema::enabled( $r['type'] ) ) { $data['post_status'] = 'draft'; }
		if ( $data['post_status'] !== $old->post_status && ( $conflict || ! current_user_can( 'msc_publish_local' ) ) ) { $data['post_status'] = $old->post_status; }
		return $data;
	}
	private static function block(): void {
		wp_register_script( 'msc-record-block', plugins_url( 'blocks/record/editor.js', MSC_FILE ), array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ), MSC_VERSION, true );
		register_block_type( dirname( MSC_FILE ) . '/blocks/record', array( 'editor_script' => 'msc-record-block', 'render_callback' => static function ( $a ) {
			$r = self::store()->resolved( $a['uuid'] ?? '' );
			if ( ! $r || ! Schema::enabled( $r['type'] ) || 'active' !== $r['state'] || empty( $r['mapping']['post_id'] ) || ! empty( $r['mapping']['suppressed'] ) || 'publish' !== get_post_status( (int) $r['mapping']['post_id'] ) || 'publish' !== $r['mapping']['status'] ) { return ''; }
			return '<aside ' . get_block_wrapper_attributes( array( 'class' => 'msc-record' ) ) . '><h3><a href="' . esc_url( get_permalink( (int) $r['mapping']['post_id'] ) ) . '">' . esc_html( $r['data']['title'] ?? '' ) . '</a></h3><p>' . esc_html( wp_strip_all_tags( $r['data']['excerpt'] ?? '' ) ) . '</p></aside>';
		} ) );
	}
}
