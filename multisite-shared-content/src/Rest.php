<?php
namespace MSC;
final class Rest {
	public static function routes(): void {
		register_rest_route( 'msc/v1', '/records', array(
			array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => array( self::class, 'listing' ) ),
			array( 'methods' => 'POST', 'permission_callback' => static fn() => current_user_can( 'msc_create_global' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->create( (string) $q['type'], self::object( $q['data'] ) ) ) ),
		) );
		register_rest_route( 'msc/v1', '/records/(?P<uuid>[a-f0-9-]{36})', array(
			array( 'methods' => 'GET', 'permission_callback' => array( self::class, 'can_read' ), 'callback' => array( self::class, 'read' ) ),
			array( 'methods' => 'PATCH', 'permission_callback' => static fn() => current_user_can( 'msc_edit_global' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->update( $q['uuid'], self::object( $q['data'] ), (int) $q['version'] ) ) ),
		) );
		register_rest_route( 'msc/v1', '/records/(?P<uuid>[a-f0-9-]{36})/local', array( 'methods' => 'PATCH', 'permission_callback' => static fn() => current_user_can( 'msc_override_local' ) || current_user_can( 'msc_publish_local' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->local( $q['uuid'], self::object( $q['values'] ), (array) ( $q['reset'] ?? array() ), (int) $q['local_version'], $q['status'] ) ) ) );
		register_rest_route( 'msc/v1', '/records/(?P<uuid>[a-f0-9-]{36})/lifecycle', array( 'methods' => 'POST', 'permission_callback' => static fn() => current_user_can( 'manage_network_options' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->update( $q['uuid'], array(), (int) $q['version'], (string) $q['state'] ) ) ) );
		register_rest_route( 'msc/v1', '/records/(?P<uuid>[a-f0-9-]{36})/revisions', array( 'methods' => 'GET', 'permission_callback' => static fn() => current_user_can( 'msc_edit_global' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->revisions( $q['uuid'] ) ) ) );
		register_rest_route( 'msc/v1', '/records/(?P<uuid>[a-f0-9-]{36})/restore', array( 'methods' => 'POST', 'permission_callback' => static fn() => current_user_can( 'msc_edit_global' ), 'callback' => static fn( $q ) => self::attempt( static fn() => Plugin::store()->restore( $q['uuid'], (int) $q['revision'], (int) $q['version'] ) ) ) );
		register_rest_route( 'msc/v1', '/queue', array( 'methods' => 'GET', 'permission_callback' => static fn() => current_user_can( 'manage_network_options' ), 'callback' => static fn() => Queue::status() ) );
	}
	private static function object( $value ): array {
		if ( null === $value ) { return array(); }
		if ( ! is_array( $value ) ) { throw new \InvalidArgumentException( 'Expected JSON object.' ); }
		return $value;
	}
	public static function attempt( callable $callback ) {
		try { return rest_ensure_response( $callback() ); }
		catch ( \InvalidArgumentException $e ) { return new \WP_Error( 'msc_invalid', $e->getMessage(), array( 'status' => 400 ) ); }
		catch ( \Throwable $e ) { return new \WP_Error( 'msc_failed', $e->getMessage(), array( 'status' => str_starts_with( $e->getMessage(), 'Conflict:' ) ? 409 : 403 ) ); }
	}
	public static function can_read( $q ): bool {
		$r = Plugin::store()->get( $q['uuid'] );
		if ( ! $r || ! Schema::enabled( $r['type'] ) || ! GamePolicy::accepts( Plugin::store()->resolved( $r['uuid'] ) ) ) { return false; }
		$m = Plugin::store()->mapping( (int) $r['id'] );
		return ( $m && $m['post_id'] && current_user_can( 'edit_post', (int) $m['post_id'] ) ) || ( 'active' === $r['state'] && $m && ! $m['suppressed'] && 'publish' === $m['status'] && 'publish' === get_post_status( (int) $m['post_id'] ) && ! post_password_required( (int) $m['post_id'] ) );
	}
	public static function read( $q ) {
		$r = GamePolicy::project( Plugin::store()->resolved( $q['uuid'] ) );
		$m = $r['mapping'];
		if ( ! current_user_can( 'edit_post', (int) ( $m['post_id'] ?? 0 ) ) ) { return array( 'uuid' => $r['uuid'], 'type' => $r['type'], 'data' => $r['data'] ); }
		return $r;
	}
	public static function listing( $q ) {
		return self::attempt( static function () use ( $q ) {
			$type = (string) ( $q['type'] ?? 'msc_show' );
			if ( ! Schema::enabled( $type ) ) { return array(); }
			$ids = Plugin::store()->query( $type, (array) ( $q['filters'] ?? array() ), get_current_blog_id(), (int) ( $q['limit'] ?? 50 ), (int) ( $q['after'] ?? 0 ) );
			$result = array();
			foreach ( $ids as $id ) { $r = GamePolicy::project( Plugin::store()->resolved( $id ) ); if ( self::can_read( array( 'uuid' => $r['uuid'] ) ) ) { $result[] = array( 'id' => $id, 'uuid' => $r['uuid'], 'type' => $type, 'data' => $r['data'] ); } }
			return $result;
		} );
	}
}
