<?php
namespace MSC;
final class Admin {
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'network_admin_menu', array( self::class, 'network_menu' ) );
		add_action( 'admin_post_msc_save', array( self::class, 'save' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'network_admin_notices', array( self::class, 'notices' ) );
		add_action( 'add_meta_boxes', array( self::class, 'metaboxes' ) );
		add_action( 'admin_enqueue_scripts', static function () {
			wp_enqueue_style( 'msc-admin', plugins_url( 'assets/admin.css', MSC_FILE ), array(), MSC_VERSION );
			if ( in_array( $_GET['page'] ?? '', array( 'msc', 'msc-acf' ), true ) ) {
				wp_enqueue_media();
				wp_enqueue_script( 'msc-media', plugins_url( 'assets/media.js', MSC_FILE ), array( 'media-editor' ), MSC_VERSION, true );
				wp_add_inline_script( 'msc-media', 'window.mscMediaSite=' . (int) get_current_blog_id() . ';', 'before' );
			}
		} );
		foreach ( Schema::all() as $type => $schema ) {
			add_filter( 'manage_' . $type . '_posts_columns', static function ( $columns ) { $columns['msc'] = 'Shared content'; return $columns; } );
			add_action( 'manage_' . $type . '_posts_custom_column', static function ( $column, $id ) { if ( 'msc' === $column ) { $r = Plugin::store()->by_post( $id ); if ( $r ) { echo '<code>' . esc_html( $r['uuid'] ) . '</code><br>' . esc_html( count( Plugin::store()->overrides( (int) $r['id'] ) ) . ' local overrides' ); } } }, 10, 2 );
		}
		add_action( 'restrict_manage_posts', static function ( $type ) {
			if ( ! isset( Schema::all()[ $type ] ) ) { return; }
			$value = sanitize_key( $_GET['msc_mode'] ?? '' );
			echo '<select name="msc_mode"><option value="">All inheritance states</option><option value="inherit" ' . selected( $value, 'inherit', false ) . '>Fully inherited</option><option value="override" ' . selected( $value, 'override', false ) . '>Has local overrides</option></select>';
		} );
		add_action( 'pre_get_posts', static function ( $q ) {
			if ( ! is_admin() || ! $q->is_main_query() || ! isset( Schema::all()[ $q->get( 'post_type' ) ?: '' ] ) ) { return; }
			$mode = sanitize_key( $_GET['msc_mode'] ?? '' );
			if ( in_array( $mode, array( 'inherit', 'override' ), true ) ) { $q->set( 'meta_query', array( array( 'key' => '_msc_override_count', 'value' => 0, 'compare' => 'inherit' === $mode ? '=' : '>', 'type' => 'NUMERIC' ) ) ); }
		} );
	}
	public static function menu(): void { add_menu_page( 'Shared Content', 'Shared Content', 'edit_posts', 'msc', array( self::class, 'page' ), 'dashicons-networking', 26 ); }
	public static function network_menu(): void { add_menu_page( 'Shared Content Network', 'Shared Content', 'msc_manage_schemas', 'msc-network', array( self::class, 'network_page' ), 'dashicons-networking' ); }
	public static function notice( string $message, string $type = 'success' ): void { set_transient( 'msc_notice_' . get_current_user_id(), array( $message, $type ), 120 ); }
	public static function notices(): void {
		$n = get_transient( 'msc_notice_' . get_current_user_id() );
		if ( $n ) { delete_transient( 'msc_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . esc_attr( $n[1] ) . '"><p>' . esc_html( $n[0] ) . '</p></div>'; }
	}
	private static function form_start( string $action ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'msc_save' ); echo '<input type="hidden" name="action" value="msc_save"><input type="hidden" name="msc_action" value="' . esc_attr( $action ) . '">';
	}
	private static function hidden( string $key, $value ): void { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
	private static function text( $value ): string { return is_array( $value ) || is_bool( $value ) ? wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : (string) $value; }
	private static function input( string $key, array $field, $value ): void {
		$name = 'fields[' . $key . ']'; $type = $field['type']; $id = 'msc-' . $key;
		if ( in_array( $type, array( 'media', 'media_list' ), true ) ) {
			Media::control( $name, $id, $value, 'media_list' === $type );
		} elseif ( ! empty( $field['taxonomy'] ) || 'multiselect' === $type && ! empty( $field['options'] ) || in_array( $type, array( 'relationship', 'relationship_list' ), true ) ) {
			$multiple = in_array( $type, array( 'multiselect', 'relationship_list' ), true );
			$options = array();
			if ( in_array( $type, array( 'relationship', 'relationship_list' ), true ) ) {
				foreach ( Plugin::store()->query( $field['target'], array(), 0, 500 ) as $related ) { $r = Plugin::store()->get( $related ); $options[ $r['uuid'] ] = $r['data']['title'] ?? $r['uuid']; }
				foreach ( (array) $value as $uuid ) { if ( $uuid && ! isset( $options[ $uuid ] ) ) { $r = Plugin::store()->get( $uuid ); $options[ $uuid ] = ( $r['data']['title'] ?? 'Unavailable record' ) . ' (current)'; } }
			} else { foreach ( Taxonomies::choices( $field, $value ) as $choice ) { $options[ $choice ] = $choice; } }
			echo '<select class="large-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . ( $multiple ? '[]' : '' ) ) . '"' . ( $multiple ? ' multiple size="6"' : '' ) . '>';
			if ( ! $multiple ) { echo '<option value="">— None —</option>'; }
			foreach ( $options as $option => $label ) { echo '<option value="' . esc_attr( $option ) . '"' . selected( in_array( (string) $option, array_map( 'strval', (array) $value ), true ), true, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</select>';
			if ( $multiple ) { echo '<p class="description">Select multiple with Command (Mac) or Ctrl. Use ACF for searchable, ordered Person links.</p>'; }
			if ( ! empty( $field['taxonomy'] ) ) { echo '<p class="description">Reusable category. Network administrators can add choices under Shared Content Network → Categories.</p>'; }
		} elseif ( in_array( $type, array( 'richtext', 'json', 'multiselect' ), true ) ) {
			echo '<textarea class="large-text" rows="' . ( 'richtext' === $type ? 5 : 3 ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( self::text( $value ) ) . '</textarea>';
			if ( 'richtext' !== $type ) { echo '<p class="description">JSON array or object.</p>'; }
		} elseif ( 'boolean' === $type || 'select' === $type && ! empty( $field['options'] ) ) {
			$options = 'boolean' === $type ? array( '0', '1' ) : $field['options'];
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $option ) { echo '<option value="' . esc_attr( $option ) . '" ' . selected( 'boolean' === $type ? (string) (int) $value : (string) $value, (string) $option, false ) . '>' . esc_html( $option ) . '</option>'; }
			echo '</select>';
		} else {
			$input_type = array( 'date' => 'date', 'time' => 'time', 'integer' => 'number', 'number' => 'number', 'url' => 'url' )[ $type ] ?? 'text';
			echo '<input class="large-text" step="any" type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( self::text( $value ) ) . '">';
			if ( 'relationship' === $type ) { echo '<p class="description">Permanent global UUID of the related record.</p>'; }
		}
	}
	public static function page(): void {
		echo '<div class="wrap msc"><h1>Shared Content</h1><p>Global information is shared across participating sites. Local overrides affect only this website.</p>';
		$type = sanitize_key( $_GET['type'] ?? 'msc_show' );
		try { $schema = Schema::get( $type ); } catch ( \Throwable $e ) { echo '<p>Unknown type.</p></div>'; return; }
		$id = sanitize_text_field( $_GET['record'] ?? '' ); $r = $id ? Plugin::store()->resolved( $id ) : null;
		if ( $id && ! $r ) { echo '<p>Record not found.</p></div>'; return; }
		if ( $r ) { $type = $r['type']; $schema = Schema::get( $type ); }
		if ( ! Schema::enabled( $type ) ) { echo '<p>A network administrator must enable this type for this site.</p></div>'; return; }
		if ( OpenImport::allowed() && isset( OpenImport::TYPES[ $type ] ) ) { echo '<p><a class="button" href="' . esc_url( add_query_arg( 'kind', $type, OpenAdmin::link( $r['uuid'] ?? '' ) ) ) . '">Search and import content</a></p>'; }
		echo '<nav class="nav-tab-wrapper">';
		foreach ( Schema::all() as $key => $s ) { if ( Schema::enabled( $key ) ) { echo '<a class="nav-tab" href="' . esc_url( admin_url( 'admin.php?page=msc&type=' . $key ) ) . '">' . esc_html( $s['label'] ) . '</a>'; } }
		echo '</nav>';
		if ( ! $r && empty( $_GET['new'] ) ) {
			if ( 'msc_episode' === $type ) { EpisodeAdmin::shared_list(); echo '</div>'; return; }
			if ( current_user_can( 'msc_create_global' ) ) { echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=msc&type=' . $type . '&new=1' ) ) . '">Create global record</a></p>'; }
			echo '<p>Use the regular post list for local publishing, search, bulk changes, and Gutenberg editing.</p><table class="widefat striped"><thead><tr><th>Title</th><th>Global identifier</th><th>Actions</th></tr></thead><tbody>';
			$after = absint( $_GET['after'] ?? 0 ); $ids = Plugin::store()->query( $type, array(), 0, 50, $after );
			foreach ( $ids as $record ) { $row = Plugin::store()->get( $record ); echo '<tr><td>' . esc_html( $row['data']['title'] ?? '(Untitled)' ) . '</td><td><code>' . esc_html( $row['uuid'] ) . '</code></td><td><a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $row['uuid'] ) ) . '">Manage fields</a></td></tr>'; }
			echo '</tbody></table>';
			if ( count( $ids ) === 50 ) { echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&type=' . $type . '&after=' . end( $ids ) ) ) . '">Next page</a></p>'; }
			echo '</div>'; return;
		}
		$global = ! $r || 'global' === ( $_GET['mode'] ?? '' );
		if ( ! current_user_can( $global ? ( $r ? 'msc_edit_global' : 'msc_create_global' ) : 'msc_override_local' ) ) { echo '<p>You do not have permission for this editing mode.</p></div>'; return; }
		if ( ! $r ) { EpisodeAdmin::new_context( $type ); }
		if ( $r ) {
			EpisodeAdmin::context( $r, $global );
			if ( function_exists( 'acf_form' ) ) { echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] . '&scope=' . ( $global ? 'global' : 'local' ) ) ) . '">Edit supported fields with ACF</a></p>'; }
			echo '<p><code>' . esc_html( $r['uuid'] ) . '</code> · Global revision ' . esc_html( $r['version'] ) . '</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] . '&mode=' . ( $global ? 'local' : 'global' ) ) ) . '">' . ( $global ? 'Switch to local overrides' : 'Switch to global editing' ) . '</a></p>';
			if ( ! $r['mapping'] ) { Queue::sync( (int) $r['id'], get_current_blog_id() ); echo '<p>Local post is queued. Refresh after synchronization.</p></div>'; return; }
		}
		echo '<div class="notice notice-' . ( $global ? 'warning' : 'info' ) . ' inline"><p><strong>' . ( $global ? 'GLOBAL EDITING — Saving changes shared information for every participating website.' : 'LOCAL EDITING — Only this website changes. Uncheck Override to reset a field to the latest global value.' ) . '</strong></p></div>';
		self::form_start( $global ? 'global' : 'local' ); self::hidden( 'type', $type ); self::hidden( 'record', $r['uuid'] ?? '' ); self::hidden( 'version', $r['version'] ?? 0 ); self::hidden( 'local_version', $r['mapping']['local_version'] ?? 0 );
		echo '<table class="form-table msc-fields"><tbody>';
		foreach ( $schema['fields'] as $key => $field ) {
			$value = $r ? ( $global ? ( $r['global'][ $key ] ?? '' ) : ( $r['data'][ $key ] ?? '' ) ) : ( 'parent_show' === $key ? sanitize_text_field( $_GET['parent'] ?? '' ) : '' );
			if ( '' === $value && in_array( $field['type'], array( 'json', 'media', 'media_list', 'multiselect', 'relationship_list' ), true ) ) { $value = array(); }
			echo '<tr><th><label for="msc-' . esc_attr( $key ) . '">' . esc_html( $field['label'] ?? ucwords( str_replace( '_', ' ', $key ) ) ) . '</label></th><td>';
			if ( ! $global ) {
				echo '<details><summary>Global value · ' . ( array_key_exists( $key, $r['overrides'] ) ? 'Overridden here' : 'Inherited' ) . '</summary>'; if ( in_array( $field['type'], array( 'media', 'media_list' ), true ) ) { echo '<div class="msc-media-previews">' . Media::previews( $r['global'][ $key ] ?? array(), 'media_list' === $field['type'] ) . '</div>'; } else { echo '<pre>' . esc_html( self::text( $r['global'][ $key ] ?? '' ) ) . '</pre>'; } echo '</details>';
				if ( false === ( $field['override'] ?? true ) ) { echo '<p>Network locked; local overrides disabled.</p></td></tr>'; continue; }
				echo '<label><input type="checkbox" name="override[' . esc_attr( $key ) . ']" value="1" ' . checked( array_key_exists( $key, $r['overrides'] ), true, false ) . '> Override on this site (including an intentionally empty value)</label>';
			}
			self::input( $key, $field, $value ); echo '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ! $global && current_user_can( 'msc_publish_local' ) ) {
			echo '<label>Publish on this website <select name="status">';
			foreach ( array( 'draft', 'publish', 'private', 'unpublished', 'removed' ) as $status ) { echo '<option ' . selected( $r['mapping']['status'], $status, false ) . '>' . esc_html( $status ) . '</option>'; }
			echo '</select></label><p class="description">Removed deletes only the local post. Choosing another status recreates it with its overrides preserved.</p>';
		}
		submit_button( $global ? 'Save global record' : 'Save local overrides' ); echo '</form>';
		if ( $r && $global && 'msc_show' === $r['type'] ) { self::episodes( $r ); }
		if ( $r && current_user_can( 'msc_edit_global' ) ) {
			echo '<details><summary>Global revision history and recovery</summary><ul>';
			foreach ( Plugin::store()->revisions( $r['id'] ) as $v ) { echo '<li>Revision ' . esc_html( $v['version'] ) . ' · ' . esc_html( $v['created_at'] ) . ' UTC · user ' . esc_html( $v['actor'] ) . '</li>'; }
			echo '</ul>'; self::form_start( 'restore' ); self::hidden( 'record', $r['uuid'] ); self::hidden( 'version', $r['version'] ); echo '<label>Restore data from revision <input type="number" min="1" name="revision" required></label>'; submit_button( 'Restore as a new global revision', 'secondary' ); echo '</form></details>';
		}
		if ( $r && current_user_can( 'manage_network_options' ) ) {
			echo '<details><summary>Global lifecycle (network administrator)</summary><p>Trashing or archiving unpublishes all local posts after propagation. Restoring preserves local publication preferences. Permanent purge is deliberately unavailable.</p>';
			self::form_start( 'lifecycle' ); self::hidden( 'record', $r['uuid'] ); self::hidden( 'version', $r['version'] ); echo '<select name="state"><option>active</option><option>trash</option><option>archived</option></select><p><label>Confirm global UUID <input class="large-text" name="confirm" required></label></p>'; submit_button( 'Change global lifecycle', 'secondary' ); echo '</form></details>';
		}
		echo '</div>';
	}
	private static function episodes( array $show ): void {
		echo '<h2>Global Episodes by season</h2>';
		foreach ( Relationships::seasons( $show['uuid'] ) as $season => $episodes ) {
			echo '<h3>' . esc_html( 'unknown' === $season ? 'Season not set' : 'Season ' . $season ) . '</h3><ul>';
			foreach ( $episodes as $e ) { echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $e['uuid'] ) ) . '">' . esc_html( EpisodeAdmin::position( $e['data'] ) . ' — ' . ( $e['data']['title'] ?? '(Untitled)' ) ) . '</a> · ' . esc_html( $e['data']['release_date'] ?? '' ) . '</li>'; }
			echo '</ul>';
		}
	}
	public static function metaboxes(): void {
		foreach ( array_keys( Schema::all() ) as $type ) { remove_meta_box( 'postimagediv', $type, 'side' ); }
		foreach ( Schema::all() as $type => $schema ) { if ( Schema::enabled( $type ) ) { add_meta_box( 'msc-inheritance', 'Shared content: local editing', array( self::class, 'metabox' ), $type, 'normal', 'high', array( '__block_editor_compatible_meta_box' => true ) ); } }
	}
	public static function metabox( \WP_Post $post ): void {
		$r = Plugin::store()->by_post( $post->ID );
		if ( ! $r ) { echo '<p>The first save creates a global record. Only users with Create Global permission can do this.</p>'; return; }
		EpisodeAdmin::context( Plugin::store()->resolved( (int) $r['id'] ) );
		$m = Plugin::store()->mapping( (int) $r['id'] );
		self::hidden( 'msc_expected_version', $m['local_version'] );
		echo '<p>Editing title, content, excerpt, or slug here creates a <strong>local override</strong>. Use Manage fields to view global values, reset inheritance, edit media, or make a global change.</p><p><code>' . esc_html( $r['uuid'] ) . '</code> · ' . esc_html( count( Plugin::store()->overrides( (int) $r['id'] ) ) ) . ' overrides · local revision ' . esc_html( $m['local_version'] ) . '</p><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] ) ) . '">Manage fields and inheritance</a></p>';
	}
	public static function network_page(): void {
		echo '<div class="wrap msc"><h1>Shared Content Network</h1><h2>Synchronization</h2><p>' . esc_html( wp_json_encode( Queue::status() ) ) . '</p><p>Run a real system cron every minute for reliable propagation: <code>wp msc queue --limit=100</code>. Pending does not mean propagated.</p>';
		global $wpdb; $jobs = $wpdb->get_results( 'SELECT job_key,attempts,state,last_error FROM ' . Database::table( 'jobs' ) . " WHERE last_error<>'' ORDER BY id LIMIT 50", ARRAY_A );
		if ( $jobs ) { echo '<pre>' . esc_html( wp_json_encode( $jobs, JSON_PRETTY_PRINT ) ) . '</pre>'; }
		self::form_start( 'retry' ); submit_button( 'Retry failed jobs', 'secondary' ); echo '</form><h2>Site participation</h2><p>Enable types per site. Disabling a type unpublishes its local posts in batches; global records and overrides remain.</p>';
		self::form_start( 'participate' ); echo '<label>Site ID <input type="number" name="site" min="1" required></label>';
		foreach ( Schema::all() as $type => $s ) { echo '<p><label><input type="checkbox" name="types[]" value="' . esc_attr( $type ) . '">' . esc_html( $s['label'] ) . '</label></p>'; }
		echo '<p>Submit the complete type selection for the site. None disables all shared types.</p>'; submit_button( 'Set site participation' ); echo '</form><h2>Sites</h2><ul>';
		foreach ( get_sites( array( 'network_id' => get_current_network_id(), 'number' => 100, 'offset' => absint( $_GET['offset'] ?? 0 ) ) ) as $site ) { echo '<li>' . esc_html( $site->blog_id . ': ' . $site->domain . $site->path . ' — ' . implode( ', ', get_blog_option( (int) $site->blog_id, 'msc_types', array() ) ) ) . '</li>'; }
		echo '</ul><p><a href="' . esc_url( network_admin_url( 'admin.php?page=msc-network&offset=' . ( absint( $_GET['offset'] ?? 0 ) + 100 ) ) ) . '">Next 100 sites</a></p><h2>Type and field schemas</h2><p>Advanced network configuration. Edit labels, slug, archive, noindex, default_status, and fields. Fields support type, label, min/max, options, target, indexed, and override:false. Back up first; changes to field types require a data migration. Existing records are not automatically converted.</p>';
		echo '<h2 id="categories">Categories</h2><p>Add reusable choices for every site. Existing values are preserved; choosing a category also assigns the native WordPress taxonomy on each site.</p>';
		self::form_start( 'category' ); echo '<label>Category <select name="taxonomy">'; foreach ( Taxonomies::definitions() as $key => $definition ) { echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $definition[0] ) . '</option>'; } echo '</select></label> <label>Name <input name="category_name" maxlength="180" required></label>'; submit_button( 'Add category choice', 'secondary' ); echo '</form>';
		self::form_start( 'schemas' ); echo '<textarea class="large-text code" rows="24" name="schemas">' . esc_textarea( wp_json_encode( Schema::all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea>'; submit_button( 'Save network schemas' ); echo '</form></div>';
	}
	public static function save(): void {
		check_admin_referer( 'msc_save' );
		$action = sanitize_key( $_POST['msc_action'] ?? '' ); $redirect = admin_url( 'admin.php?page=msc' );
		try {
			$id = sanitize_text_field( $_POST['record'] ?? '' ); $r = $id ? Plugin::store()->get( $id ) : null;
			if ( in_array( $action, array( 'global', 'local' ), true ) ) {
				$type = $r['type'] ?? sanitize_key( $_POST['type'] ?? '' ); $schema = Schema::get( $type ); $values = array(); $reset = array();
				foreach ( $schema['fields'] as $key => $field ) {
					if ( 'local' === $action && empty( $_POST['override'][ $key ] ) ) { if ( false !== ( $field['override'] ?? true ) ) { $reset[] = $key; } continue; }
					$value = wp_unslash( $_POST['fields'][ $key ] ?? '' );
					if ( in_array( $field['type'], array( 'json', 'media', 'media_list', 'multiselect', 'relationship_list' ), true ) ) { $value = is_array( $value ) ? $value : json_decode( $value ?: '[]', true, 32, JSON_THROW_ON_ERROR ); }
					$values[ $key ] = $value;
				}
				if ( 'global' === $action ) {
					$r = $r ? Plugin::store()->update( $id, $values, absint( $_POST['version'] ) ) : Plugin::store()->create( $type, $values );
					Plugin::materializer()->sync( (int) $r['id'], get_current_blog_id() );
					self::notice( 'Global revision saved. Other sites are queued; check synchronization status for propagation.' );
				} else { Plugin::store()->local( $id, $values, $reset, absint( $_POST['local_version'] ), isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : null ); self::notice( 'Local overrides saved.' ); }
				$redirect = admin_url( 'admin.php?page=msc&record=' . $r['uuid'] . ( 'global' === $action ? '&mode=global' : '' ) );
			} elseif ( 'category' === $action ) { Taxonomies::add_choice( sanitize_key( $_POST['taxonomy'] ?? '' ), wp_unslash( $_POST['category_name'] ?? '' ) ); self::notice( 'Category choice added for all sites.' ); $redirect = network_admin_url( 'admin.php?page=msc-network#categories' ); } elseif ( 'schemas' === $action ) { Schema::save( json_decode( wp_unslash( $_POST['schemas'] ), true, 64, JSON_THROW_ON_ERROR ) ); self::notice( 'Schemas saved. Re-save participation to rematerialize sites after schema changes.' ); $redirect = network_admin_url( 'admin.php?page=msc-network' ); }
			elseif ( 'participate' === $action ) { Schema::participate( absint( $_POST['site'] ), array_map( 'sanitize_key', (array) ( $_POST['types'] ?? array() ) ) ); self::notice( 'Participation saved; materialization is queued.' ); $redirect = network_admin_url( 'admin.php?page=msc-network' ); }
			elseif ( 'retry' === $action ) { Queue::retry(); self::notice( 'Failed jobs queued for retry.' ); $redirect = network_admin_url( 'admin.php?page=msc-network' ); }
			elseif ( 'restore' === $action ) { Plugin::store()->restore( $id, absint( $_POST['revision'] ), absint( $_POST['version'] ) ); self::notice( 'Global data restored as a new revision; propagation queued.' ); $redirect .= '&record=' . rawurlencode( $id ); }
			elseif ( 'lifecycle' === $action ) { if ( ! hash_equals( $id, sanitize_text_field( $_POST['confirm'] ?? '' ) ) ) { throw new \InvalidArgumentException( 'Confirmation UUID does not match.' ); } Plugin::store()->update( $id, array(), absint( $_POST['version'] ), sanitize_key( $_POST['state'] ) ); self::notice( 'Global lifecycle changed; propagation queued.' ); $redirect .= '&record=' . rawurlencode( $id ); }
			else { throw new \InvalidArgumentException( 'Unknown action.' ); }
		} catch ( \Throwable $e ) { self::notice( $e->getMessage(), 'error' ); $redirect = wp_get_referer() ?: $redirect; }
		wp_safe_redirect( $redirect ); exit;
	}
}
