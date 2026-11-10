<?php
namespace MSC;

/** Optional ACF Free adapter. The MSC store remains authoritative. */
final class Acf {
	private static array $fields = array();
	private static ?array $editing = null;
	private static bool $global = false;
	public static function key( string $type, string $name ): string { return 'field_msc_' . substr( hash( 'sha256', $type . ':' . $name ), 0, 24 ); }
	public static function boot(): void {
		add_action( 'acf/init', array( self::class, 'register' ) );
		add_filter( 'acf/load_reference', array( self::class, 'reference' ), 10, 3 );
		add_filter( 'acf/pre_load_value', array( self::class, 'load' ), 10, 3 );
		add_filter( 'acf/pre_update_value', array( self::class, 'write' ), 10, 4 );
		add_filter( 'acf/prepare_field', array( self::class, 'prepare' ) );
		add_action( 'acf/render_field', array( self::class, 'override_control' ) );
		add_action( 'admin_post_msc_acf_save', array( self::class, 'handle' ) );
		add_action( 'admin_menu', static function () { add_submenu_page( null, 'ACF Shared Fields', 'ACF Shared Fields', 'edit_posts', 'msc-acf', array( self::class, 'page' ) ); } );
		add_action( 'admin_enqueue_scripts', static function () { if ( 'msc-acf' === ( $_GET['page'] ?? '' ) && function_exists( 'acf_enqueue_scripts' ) ) { acf_enqueue_scripts(); } } );
	}
	public static function register(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }
		self::$fields = array();
		if ( ! acf_get_field_type( 'msc_media' ) ) { new AcfMediaField(); }
		$types = array( 'text' => 'text', 'richtext' => 'wysiwyg', 'integer' => 'number', 'number' => 'number', 'boolean' => 'true_false', 'date' => 'date_picker', 'time' => 'time_picker', 'timezone' => 'select', 'url' => 'url', 'select' => 'select', 'multiselect' => 'checkbox', 'relationship' => 'post_object', 'relationship_list' => 'post_object', 'media' => 'msc_media', 'media_list' => 'msc_media' );
		foreach ( Schema::all() as $type => $schema ) {
			$fields = array();
			foreach ( $schema['fields'] as $name => $definition ) {
				if ( ! empty( $definition['hidden'] ) || isset( $definition['property'] ) || ! isset( $types[ $definition['type'] ] ) ) { continue; }
				$key = self::key( $type, $name );
				$field = array( 'key' => $key, 'name' => 'msc_' . $name, 'label' => $definition['label'] ?? ucwords( str_replace( '_', ' ', $name ) ), 'type' => $types[ $definition['type'] ], 'msc_type' => $type, 'msc_name' => $name, 'required' => 0 );
				if ( ! empty( $definition['taxonomy'] ) ) { $field['type'] = 'select'; $field['multiple'] = 'multiselect' === $definition['type']; $field['ui'] = 1; $choices = Taxonomies::choices( $definition ); $field['choices'] = $choices ? array_combine( $choices, $choices ) : array(); $field['allow_null'] = 1; }
				elseif ( in_array( $definition['type'], array( 'select', 'multiselect', 'timezone' ), true ) ) { $choices = 'timezone' === $definition['type'] ? \DateTimeZone::listIdentifiers() : ( $definition['options'] ?? array() ); $field['choices'] = $choices ? array_combine( $choices, $choices ) : array(); $field['allow_null'] = 1; }
				if ( ! empty( $definition['multiline'] ) ) { $field['type'] = 'textarea'; $field['rows'] = 4; $field['new_lines'] = ''; $field['instructions'] = $definition['instructions'] ?? ''; }
				if ( 'date' === $definition['type'] ) { $field['display_format'] = 'Y-m-d'; $field['return_format'] = 'Y-m-d'; }
				if ( 'time' === $definition['type'] ) { $field['display_format'] = 'H:i:s'; $field['return_format'] = 'H:i:s'; }
				if ( in_array( $definition['type'], array( 'relationship', 'relationship_list' ), true ) ) { $field['post_type'] = array( $definition['target'] ); $field['return_format'] = 'id'; $field['multiple'] = 'relationship_list' === $definition['type'] ? 1 : 0; $field['allow_null'] = 1; }
				if ( in_array( $definition['type'], array( 'media', 'media_list' ), true ) ) { $field['msc_multiple'] = 'media_list' === $definition['type']; }
				foreach ( array( 'min', 'max', 'step' ) as $bound ) { if ( isset( $definition[ $bound ] ) ) { $field[ $bound ] = $definition[ $bound ]; } }
				if ( 'integer' === $definition['type'] ) { $field['step'] = 1; }
				self::$fields[ $key ] = $field; $fields[] = $field;
			}
			// Dedicated forms avoid native Gutenberg post saves ambiguously changing scope.
			acf_remove_local_field_group( 'group_msc_' . $type );
			acf_add_local_field_group( array( 'key' => 'group_msc_' . $type, 'title' => $schema['label'] . ' — Shared fields', 'fields' => $fields, 'location' => array(), 'active' => true, 'show_in_rest' => 0 ) );
		}
	}
	public static function prepare( $field ) {
		if ( ! isset( self::$fields[ $field['key'] ?? '' ] ) ) { return $field; }
		$definition = Schema::get( $field['msc_type'] )['fields'][ $field['msc_name'] ];
		if ( self::$editing && in_array( $definition['type'], array( 'relationship', 'relationship_list' ), true ) && ! Schema::enabled( $definition['target'] ) ) { return false; }
		if ( ! empty( $definition['taxonomy'] ) ) {
			$current = self::$editing ? ( self::$global ? self::$editing['global'] : self::$editing['data'] )[ $field['msc_name'] ] ?? array() : $field['value'] ?? array();
			$choices = Taxonomies::choices( $definition, $current ); $field['choices'] = $choices ? array_combine( $choices, $choices ) : array();
		}
		return $field;
	}
	public static function reference( $reference, $name, $post_id ) {
		if ( ! is_numeric( $post_id ) || ! str_starts_with( $name, 'msc_' ) ) { return $reference; }
		$r = Plugin::store()->by_post( (int) $post_id );
		$key = $r ? self::key( $r['type'], substr( $name, 4 ) ) : '';
		return isset( self::$fields[ $key ] ) ? $key : $reference;
	}
	private static function record( $post_id, array $field ): ?array {
		if ( ! is_numeric( $post_id ) || ! isset( self::$fields[ $field['key'] ?? '' ] ) ) { return null; }
		$r = Plugin::store()->resolved( Plugin::store()->by_post( (int) $post_id )['id'] ?? 0 );
		return $r && $r['type'] === $field['msc_type'] && Schema::enabled( $r['type'] ) ? $r : null;
	}
	private static function to_acf( array $field, $value ) {
		$type = Schema::get( $field['msc_type'] )['fields'][ $field['msc_name'] ]['type'];
		if ( 'date' === $type ) { return str_replace( '-', '', (string) $value ); }
		if ( 'relationship' === $type ) { return $value ? ( msc_local_post_id( $value ) ?? 0 ) : 0; }
		if ( 'relationship_list' === $type ) { return array_values( array_filter( array_map( static fn( $uuid ) => msc_local_post_id( $uuid ) ?? 0, (array) $value ) ) ); }
		if ( in_array( $type, array( 'media', 'media_list' ), true ) ) { return is_array( $value ) ? $value : array(); }
		if ( 'time' === $type && strlen( (string) $value ) === 5 ) { return $value . ':00'; }
		return $value ?? '';
	}
	private static function from_acf( array $field, $value ) {
		$definition = Schema::get( $field['msc_type'] )['fields'][ $field['msc_name'] ];
		if ( in_array( $definition['type'], array( 'media', 'media_list' ), true ) && is_string( $value ) ) { $value = json_decode( $value ?: '[]', true, 32, JSON_THROW_ON_ERROR ); }
		if ( 'relationship_list' === $definition['type'] ) { $value = array_map( static function( $post ) { $r = Plugin::store()->by_post( (int) $post ); if ( ! $r ) { throw new \InvalidArgumentException( 'Related record is not a shared record on this site.' ); } return $r['uuid']; }, (array) ( $value ?: array() ) ); }
		if ( 'relationship' === $definition['type'] && ! $value ) { $value = ''; }
		if ( 'date' === $definition['type'] && preg_match( '/^\d{8}$/D', (string) $value ) ) { $value = substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ); }
		if ( 'relationship' === $definition['type'] && $value ) { $related = Plugin::store()->by_post( (int) $value ); if ( ! $related ) { throw new \InvalidArgumentException( 'Related post is not a shared record on this site.' ); } $value = $related['uuid']; }
		if ( 'multiselect' === $definition['type'] && in_array( $value, array( '', false, null ), true ) ) { $value = array(); }
		return Schema::sanitize( $definition, $value );
	}
	public static function load( $check, $post_id, $field ) {
		$r = self::record( $post_id, $field ); if ( ! $r ) { return $check; }
		$values = self::$editing && self::$editing['uuid'] === $r['uuid'] && self::$global ? $r['global'] : $r['data'];
		return self::to_acf( $field, $values[ $field['msc_name'] ] ?? '' );
	}
	public static function write( $check, $value, $post_id, $field ) {
		$r = self::record( $post_id, $field ); if ( ! $r ) { return $check; }
		if ( Materializer::$writing ) { return true; }
		try {
			Plugin::store()->local( $r['id'], array( $field['msc_name'] => self::from_acf( $field, $value ) ), array(), (int) $r['mapping']['local_version'] );
			return true;
		} catch ( \Throwable $e ) { return false; }
	}
	/** Atomic explicit-scope submission. Programmatic update_field() is always local. */
	public static function save( array $request ): array {
		$post = absint( $request['post_id'] ?? 0 ); $r = Plugin::store()->by_post( $post );
		if ( ! $r || ! current_user_can( 'edit_post', $post ) || ! wp_verify_nonce( $request['nonce'] ?? '', 'msc_acf_' . $post ) ) { throw new \RuntimeException( 'Invalid request or permission denied.' ); }
		$scope = $request['scope'] ?? ''; if ( ! in_array( $scope, array( 'global', 'local' ), true ) ) { throw new \InvalidArgumentException( 'Choose global or local scope.' ); }
		Permissions::require( 'global' === $scope ? 'msc_edit_global' : 'msc_override_local', $r['type'] );
		$publication = $request['publication'] ?? '';
		if ( ! is_string( $publication ) || ! in_array( $publication, array( '', 'site', 'global' ), true ) ) { throw new \InvalidArgumentException( 'Invalid publication action.' ); }
		if ( $publication ) { Publication::require( $publication, $r ); }
		$values = array(); $reset = array();
		foreach ( (array) ( $request['acf'] ?? array() ) as $key => $raw ) {
			$field = self::$fields[ $key ] ?? null;
			if ( ! $field || $field['msc_type'] !== $r['type'] ) { throw new \InvalidArgumentException( 'Unsupported or mismatched shared ACF field.' ); }
			$name = $field['msc_name']; $definition = Schema::get( $r['type'] )['fields'][ $name ];
			if ( 'local' === $scope && false === ( $definition['override'] ?? true ) ) { continue; }
			if ( 'local' === $scope && empty( $request['override'][ $name ] ) ) { $reset[] = $name; }
			else {
				if ( 'relationship_list' === $definition['type'] ) { $previous = 'global' === $scope ? $r['data'][ $name ] ?? array() : Plugin::store()->resolved( $r['id'] )['data'][ $name ] ?? array(); foreach ( (array) $previous as $uuid ) { if ( ! msc_local_post_id( $uuid ) ) { throw new \InvalidArgumentException( 'A related record is unavailable on this site. Use All fields and publishing to manage these links.' ); } } }
				if ( 'global' === $scope && 'relationship' === $definition['type'] && ! $raw && ! empty( $r['data'][ $name ] ) && ! msc_local_post_id( $r['data'][ $name ] ) ) { throw new \InvalidArgumentException( 'Related record is unavailable on this site. Use the Shared Content UUID editor.' ); }
				$values[ $name ] = self::from_acf( $field, $raw );
			}
		}
		$save = static function () use ( $r, $values, $reset, $request, $scope ) { return 'global' === $scope ? Plugin::store()->update( $r['id'], $values, (int) ( $request['version'] ?? 0 ) ) : Plugin::store()->local( $r['id'], $values, $reset, (int) ( $request['local_version'] ?? 0 ) ); };
		if ( ! $publication ) { return $save(); }
		return Database::transaction( static function () use ( $save, $scope, $request, $publication, $r ) {
			$saved = $save(); $version = 'local' === $scope ? (int) $saved['mapping']['local_version'] : (int) ( $request['local_version'] ?? 0 );
			$result = Publication::publish( (int) $r['id'], $publication, $version );
			$saved = 'global' === $scope ? Plugin::store()->get( $r['id'] ) : Plugin::store()->resolved( $r['id'] ); $saved['publication'] = $result; return $saved;
		} );
	}
	public static function handle(): void {
		try { $r = self::save( wp_unslash( $_POST ) ); if ( 'global' === $_POST['scope'] ) { Propagation::send( $r ); } if ( ! empty( $r['publication'] ) ) { Admin::notice( 'Fields saved. Published on ' . count( $r['publication']['sites'] ) . ( 1 === count( $r['publication']['sites'] ) ? ' site.' : ' sites.' ) ); } wp_safe_redirect( admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] . '&scope=' . sanitize_key( $_POST['scope'] ) . '&updated=1' ) ); exit; }
		catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ), 'Shared fields were not saved', array( 'response' => str_starts_with( $e->getMessage(), 'Conflict:' ) ? 409 : ( $e instanceof \InvalidArgumentException ? 400 : 403 ), 'back_link' => true ) ); }
	}
	public static function override_control( array $field ): void {
		if ( ! self::$editing || ! isset( self::$fields[ $field['key'] ?? '' ] ) ) { return; }
		$name = $field['msc_name'];
		if ( self::$global ) { echo '<p class="description">Shared value: this save updates every inheriting site.</p>'; return; }
		if ( false === ( Schema::get( $field['msc_type'] )['fields'][ $name ]['override'] ?? true ) ) { echo '<p>Network locked; this value cannot be overridden.</p>'; return; }
		echo '<label><input type="checkbox" name="override[' . esc_attr( $name ) . ']" value="1" ' . checked( array_key_exists( $name, self::$editing['overrides'] ), true, false ) . '> Override on this site, including an intentionally empty value. Uncheck to inherit.</label>';
		if ( in_array( $field['type'], array( 'msc_media' ), true ) ) { echo '<details><summary>Global image preview</summary><div class="msc-media-previews">' . Media::previews( self::$editing['global'][ $name ] ?? array(), ! empty( $field['msc_multiple'] ) ) . '</div></details>'; } else { echo '<p class="description">Current global value: ' . esc_html( wp_json_encode( self::$editing['global'][ $name ] ?? '' ) ) . '</p>'; }
	}
	public static function page(): void {
		if ( ! function_exists( 'acf_form' ) ) { wp_die( 'Activate Advanced Custom Fields to use this editor.' ); }
		$r = Plugin::store()->resolved( sanitize_text_field( $_GET['record'] ?? '' ) );
		if ( ! $r || empty( $r['mapping']['post_id'] ) || ! current_user_can( 'edit_post', (int) $r['mapping']['post_id'] ) ) { wp_die( 'A local shared post and edit permission are required.' ); }
		self::$global = Admin::global_scope( $_GET['scope'] ?? '' );
		try { Permissions::require( self::$global ? 'msc_edit_global' : 'msc_override_local', $r['type'] ); } catch ( \Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
		self::$editing = $r;
		echo '<div class="wrap"><h1>' . esc_html( Schema::get( $r['type'] )['label'] ) . ' — ACF Shared Fields</h1><h2>' . esc_html( $r['data']['title'] ?? '' ) . '</h2><p><strong>' . ( self::$global ? 'GLOBAL EDITING — Changes affect every inheriting site.' : 'LOCAL EDITING — Check Override to customize a field; uncheck to reset it.' ) . '</strong></p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc-acf&record=' . $r['uuid'] . '&scope=' . ( self::$global ? 'local' : 'global' ) ) ) . '">Switch to ' . ( self::$global ? 'local overrides' : 'global editing' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=msc&record=' . $r['uuid'] . '&mode=' . ( self::$global ? 'global' : 'local' ) ) ) . '">All fields and publishing</a></p><p>Person links, categories and image previews are available below. Native title/body and complex JSON fields use All fields and publishing.</p>';
		EpisodeAdmin::context( $r, self::$global ); ComicAdmin::context( $r, self::$global );
		PersonReview::notice( $r );
		$status = get_post_status_object( get_post_status( (int) $r['mapping']['post_id'] ) );
		echo '<p class="msc-acf-publication-status"><strong>Publication on this site: ' . esc_html( $status->label ?? 'Unavailable' ) . '</strong>' . ( 'publish' === get_post_status( (int) $r['mapping']['post_id'] ) ? ' · <a href="' . esc_url( get_permalink( (int) $r['mapping']['post_id'] ) ) . '" target="_blank" rel="noopener">View post</a>' : '' ) . '</p>';
		if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success inline"><p>Shared fields saved. See Site synchronization below for global delivery and local overrides.</p></div>'; }
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		foreach ( array( 'action' => 'msc_acf_save', 'post_id' => $r['mapping']['post_id'], 'nonce' => wp_create_nonce( 'msc_acf_' . $r['mapping']['post_id'] ), 'scope' => self::$global ? 'global' : 'local', 'version' => $r['version'], 'local_version' => $r['mapping']['local_version'] ) as $name => $value ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'; }
		acf_form( array( 'post_id' => (int) $r['mapping']['post_id'], 'field_groups' => array( 'group_msc_' . $r['type'] ), 'form' => false, 'honeypot' => false ) );
		submit_button( self::$global ? 'Save shared fields globally' : 'Save local overrides' );
		if ( current_user_can( 'msc_publish_local' ) && 'active' === $r['state'] ) {
			echo '<div class="msc-acf-publish-actions"><button type="submit" class="button button-secondary" name="publication" value="site">Save and publish on this site</button>';
			if ( Publication::global_allowed() ) { echo ' <button type="submit" class="button button-secondary" name="publication" value="global">Save and publish globally</button>'; }
			echo '<p class="description">Field changes follow the selected editing mode. Global publication targets enabled sites, preserves local field overrides and excludes local removals and unsupported platforms.</p></div>';
		}
		echo '</form>'; if ( self::$global ) { Propagation::render( $r ); } echo '</div>';
		self::$editing = null;
	}
}
