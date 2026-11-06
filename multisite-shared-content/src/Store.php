<?php
namespace MSC;

final class Store {
	public function get( $id ): ?array {
		global $wpdb;
		// Fresh authoritative reads avoid persistent object-cache races after CAS updates.
		$numeric = is_int( $id ) || ctype_digit( (string) $id );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'records' ) . ' WHERE ' . ( $numeric ? 'id=%d' : 'uuid=%s' ), $id ), ARRAY_A );
		if ( ! $row ) { return null; }
		$row['data'] = json_decode( $row['data'], true, 64, JSON_THROW_ON_ERROR );
		return $row;
	}
	public function create( string $type, array $data ): array {
		Permissions::require( 'msc_create_global', $type );
		$data = Schema::values( $type, $data );
		return Database::transaction( function () use ( $type, $data ) {
			global $wpdb;
			Database::check( $wpdb->insert( Database::table( 'records' ), array( 'uuid' => wp_generate_uuid4(), 'type' => $type, 'data' => wp_json_encode( $data ), 'author' => get_current_user_id(), 'updated_at' => current_time( 'mysql', true ) ) ) );
			$r = $this->get( (int) $wpdb->insert_id );
			$this->revision( $r ); $this->index( $r, 0, $data ); Queue::fanout( (int) $r['id'] );
			return $r;
		} );
	}
	public function update( $id, array $data, int $version, ?string $state = null ): array {
		$r = $this->get( $id );
		if ( ! $r ) { throw new \InvalidArgumentException( 'Record not found.' ); }
		Permissions::require( $state ? 'msc_trash_global' : 'msc_edit_global', $r['type'] );
		if ( $state && ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Only network administrators can change global lifecycle state.' ); }
		if ( $state && ! in_array( $state, array( 'active', 'trash', 'archived' ), true ) ) { throw new \InvalidArgumentException( 'Invalid global state.' ); }
		$clean = Schema::values( $r['type'], $data );
		return Database::transaction( function () use ( $r, $clean, $version, $state ) {
			global $wpdb;
			$r['data'] = array_replace( $r['data'], $clean );
			$result = Database::check( $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::table( 'records' ) . ' SET data=%s, state=%s, version=version+1, updated_at=%s WHERE id=%d AND version=%d', wp_json_encode( $r['data'] ), $state ?? $r['state'], current_time( 'mysql', true ), $r['id'], $version ) ) );
			if ( 1 !== $result ) { throw new \RuntimeException( 'Conflict: global record changed. Reload before saving.' ); }
			$r = $this->get( (int) $r['id'] ); $this->revision( $r ); $this->index( $r, 0, $r['data'] ); Queue::fanout( (int) $r['id'] );
			return $r;
		} );
	}
	private function revision( array $r ): void {
		global $wpdb;
		Database::check( $wpdb->insert( Database::table( 'revisions' ), array( 'record_id' => $r['id'], 'version' => $r['version'], 'actor' => get_current_user_id(), 'site_id' => get_current_blog_id(), 'snapshot' => wp_json_encode( array( 'data' => $r['data'], 'state' => $r['state'] ) ), 'created_at' => current_time( 'mysql', true ) ) ) );
	}
	public function revisions( $id ): array {
		global $wpdb; $r = $this->get( $id );
		if ( ! $r ) { return array(); }
		Permissions::require( 'msc_edit_global', $r['type'] );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'revisions' ) . ' WHERE record_id=%d ORDER BY version DESC LIMIT 100', $r['id'] ), ARRAY_A );
	}
	public function restore( $id, int $revision, int $expected ): array {
		global $wpdb; $r = $this->get( $id );
		if ( ! $r ) { throw new \InvalidArgumentException( 'Record not found.' ); }
		$row = $wpdb->get_var( $wpdb->prepare( 'SELECT snapshot FROM ' . Database::table( 'revisions' ) . ' WHERE record_id=%d AND version=%d', $r['id'], $revision ) );
		if ( ! $row ) { throw new \InvalidArgumentException( 'Revision not found.' ); }
		$snapshot = json_decode( $row, true, 64, JSON_THROW_ON_ERROR );
		return $this->update( $id, $snapshot['data'], $expected );
	}
	public function mapping( int $id, ?int $site = null ): ?array {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'maps' ) . ' WHERE record_id=%d AND site_id=%d', $id, $site ?? get_current_blog_id() ), ARRAY_A );
	}
	public function by_post( int $post, ?int $site = null ): ?array {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT record_id FROM ' . Database::table( 'maps' ) . ' WHERE site_id=%d AND post_id=%d', $site ?? get_current_blog_id(), $post ) );
		return $id ? $this->get( (int) $id ) : null;
	}
	public function overrides( int $id, ?int $site = null ): array {
		global $wpdb; $result = array();
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT field,value FROM ' . Database::table( 'overrides' ) . ' WHERE record_id=%d AND site_id=%d', $id, $site ?? get_current_blog_id() ), ARRAY_A );
		foreach ( $rows as $row ) { $result[ $row['field'] ] = json_decode( $row['value'], true, 64, JSON_THROW_ON_ERROR ); }
		return $result;
	}
	public function resolved( $id, ?int $site = null ): ?array {
		$r = $this->get( $id );
		if ( ! $r ) { return null; }
		$site = $site ?? get_current_blog_id(); $r['mapping'] = $this->mapping( (int) $r['id'], $site );
		$key = get_current_network_id() . ':' . $r['id'] . ':' . $site . ':' . $r['version'] . ':' . ( $r['mapping']['local_version'] ?? 0 );
		$cached = wp_cache_get( $key, 'msc_resolved' );
		$r['overrides'] = false === $cached ? $this->overrides( (int) $r['id'], $site ) : $cached;
		if ( false === $cached ) { wp_cache_set( $key, $r['overrides'], 'msc_resolved', 300 ); }
		$r['global'] = $r['data']; $r['data'] = array_replace( $r['data'], $r['overrides'] );
		return $r;
	}
	public function local( $id, array $values, array $reset, int $version, ?string $status = null ): array {
		$r = $this->get( $id );
		if ( ! $r ) { throw new \InvalidArgumentException( 'Record not found.' ); }
		Permissions::require( $values || $reset || ! $status ? 'msc_override_local' : 'msc_publish_local', $r['type'] );
		$schema = Schema::get( $r['type'] );
		foreach ( array_unique( array_merge( array_keys( $values ), $reset ) ) as $key ) {
			if ( ! isset( $schema['fields'][ $key ] ) || false === ( $schema['fields'][ $key ]['override'] ?? true ) ) { throw new \InvalidArgumentException( 'Field cannot be overridden: ' . $key ); }
		}
		if ( $status ) {
			Permissions::require( 'msc_publish_local', $r['type'] );
			if ( ! in_array( $status, array( 'draft', 'publish', 'private', 'unpublished', 'removed' ), true ) ) { throw new \InvalidArgumentException( 'Invalid local status.' ); }
		}
		$values = Schema::values( $r['type'], $values ); $site = get_current_blog_id();
		Database::transaction( function () use ( $r, $values, $reset, $version, $status, $site ) {
			global $wpdb;
			$changed = Database::check( $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::table( 'maps' ) . ' SET local_version=local_version+1' . ( $status ? ',status=%s,suppressed=%d' : '' ) . ' WHERE record_id=%d AND site_id=%d AND local_version=%d', ...array_merge( $status ? array( $status, (int) ( 'removed' === $status ) ) : array(), array( $r['id'], $site, $version ) ) ) ) );
			if ( 1 !== $changed ) { throw new \RuntimeException( 'Conflict: local record changed or is not materialized. Reload.' ); }
			foreach ( $values as $key => $value ) { Database::check( $wpdb->replace( Database::table( 'overrides' ), array( 'record_id' => $r['id'], 'site_id' => $site, 'field' => $key, 'value' => wp_json_encode( $value ) ) ) ); }
			foreach ( $reset as $key ) { Database::check( $wpdb->delete( Database::table( 'overrides' ), array( 'record_id' => $r['id'], 'site_id' => $site, 'field' => $key ) ) ); }
			Queue::sync( (int) $r['id'], $site );
		} );
		Plugin::materializer()->sync( (int) $r['id'], $site );
		return $this->resolved( $id );
	}
	public function index( array $r, int $site, array $data ): void {
		global $wpdb;
		Database::check( $wpdb->delete( Database::table( 'lookup' ), array( 'record_id' => $r['id'], 'site_id' => $site ) ) );
		foreach ( Schema::get( $r['type'] )['fields'] as $key => $field ) {
			if ( empty( $field['indexed'] ) || ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) { continue; }
			Database::check( $wpdb->insert( Database::table( 'lookup' ), array( 'record_id' => $r['id'], 'site_id' => $site, 'field' => $key, 'text_value' => (string) $data[ $key ], 'number_value' => is_numeric( $data[ $key ] ) ? $data[ $key ] : null ) ) );
		}
		ReleaseCalendar::index( $r, $site, $data );
	}
	/** Indexed global/site-materialized queries. Returns IDs; pagination is bounded. */
	public function query( string $type, array $filters = array(), int $site = 0, int $limit = 50, int $after = 0 ): array {
		global $wpdb; $schema = Schema::get( $type ); $args = array( $type, $after );
		$sql = 'SELECT r.id FROM ' . Database::table( 'records' ) . " r WHERE r.type=%s AND r.id>%d AND r.state='active'";
		if ( $site ) {
			$sql .= ' AND EXISTS (SELECT 1 FROM ' . Database::table( 'maps' ) . " m WHERE m.record_id=r.id AND m.site_id=%d AND m.suppressed=0 AND m.status='publish')"; $args[] = $site;
		}
		foreach ( $filters as $field => $value ) {
			if ( empty( $schema['fields'][ $field ]['indexed'] ) ) { throw new \InvalidArgumentException( 'Query field must be indexed.' ); }
			$op = '=';
			if ( is_array( $value ) ) { $op = $value['operator'] ?? '='; $value = $value['value'] ?? ''; }
			if ( ! in_array( $op, array( '=', '>=', '<=', '>', '<' ), true ) ) { throw new \InvalidArgumentException( 'Unsupported comparison.' ); }
			if ( ! is_scalar( $value ) ) { throw new \InvalidArgumentException( 'Query comparison value must be scalar.' ); }
			$number = in_array( $schema['fields'][ $field ]['type'], array( 'number', 'integer' ), true );
			if ( $number && ! is_numeric( $value ) ) { throw new \InvalidArgumentException( 'Numeric query value required.' ); }
			$sql .= ' AND EXISTS (SELECT 1 FROM ' . Database::table( 'lookup' ) . ' x WHERE x.record_id=r.id AND x.site_id=%d AND x.field=%s AND x.' . ( $number ? 'number_value' : 'text_value' ) . $op . ( $number ? '%f' : '%s' ) . ')';
			array_push( $args, $site, $field, $value );
		}
		$sql .= ' ORDER BY r.id LIMIT %d'; $args[] = min( 500, max( 1, $limit ) );
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) ) );
	}
}
