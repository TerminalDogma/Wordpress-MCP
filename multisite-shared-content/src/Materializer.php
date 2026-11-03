<?php
namespace MSC;
final class Materializer {
	public static bool $writing = false;
	public static array $native_versions = array();
	public function sync( int $id, int $site ): void {
		Database::lock( "record:$id:$site", function () use ( $id, $site ) {
			return Database::transaction( function () use ( $id, $site ) {
			global $wpdb; $store = Plugin::store(); $r = $store->get( $id ); $blog = get_site( $site );
			if ( ! $r || ! $blog || (int) $blog->network_id !== get_current_network_id() || $blog->deleted || $blog->spam || $blog->archived ) { return; }
			switch_to_blog( $site ); self::$writing = true;
			try {
				$schema = Schema::get( $r['type'] );
				if ( ! post_type_exists( $r['type'] ) ) { Plugin::register_posts(); }
				$m = $store->mapping( $id, $site ); $enabled = Schema::enabled( $r['type'], $site );
				if ( ! $m && ! $enabled ) { return; }
				if ( ! $m ) {
					Database::check( $wpdb->insert( Database::table( 'maps' ), array( 'record_id' => $id, 'site_id' => $site, 'status' => $schema['default_status'] ?? 'draft' ) ) );
					$m = $store->mapping( $id, $site );
				}
				$values = array_replace( $r['data'], $store->overrides( $id, $site ) );
				if ( $m['suppressed'] ) {
					if ( $m['post_id'] && get_post( (int) $m['post_id'] ) ) { if ( ! wp_delete_post( (int) $m['post_id'], true ) ) { throw new \RuntimeException( 'Local deletion was prevented by a WordPress hook.' ); } }
					Database::check( $wpdb->update( Database::table( 'maps' ), array( 'post_id' => null, 'version' => $r['version'] ), array( 'record_id' => $id, 'site_id' => $site ) ) );
					$wpdb->delete( Database::table( 'lookup' ), array( 'record_id' => $id, 'site_id' => $site ) );
					return;
				}
				$post = array( 'post_type' => $r['type'], 'post_status' => ! $enabled || 'active' !== $r['state'] || 'unpublished' === $m['status'] ? 'draft' : $m['status'] );
				foreach ( $schema['fields'] as $key => $field ) {
					if ( isset( $field['property'] ) ) { $post[ $field['property'] ] = $values[ $key ] ?? ''; }
				}
				if ( $m['post_id'] && get_post( (int) $m['post_id'] ) ) { $post['ID'] = (int) $m['post_id']; }
				// Recover a post written before a crashed mapping update; reserved meta never editable through REST.
				if ( empty( $post['ID'] ) ) {
					$existing = get_posts( array( 'post_type' => $r['type'], 'post_status' => 'any', 'meta_key' => '_msc_uuid', 'meta_value' => $r['uuid'], 'numberposts' => 1, 'fields' => 'ids' ) );
					if ( $existing ) { $post['ID'] = $existing[0]; }
				}
				$post['meta_input'] = array( '_msc_uuid' => $r['uuid'] );
				$post_id = wp_insert_post( wp_slash( $post ), true );
				if ( is_wp_error( $post_id ) ) { throw new \RuntimeException( $post_id->get_error_message() ); }
				foreach ( $schema['fields'] as $key => $field ) {
					if ( ! isset( $field['property'] ) ) { update_post_meta( $post_id, 'msc_' . $key, wp_slash( $values[ $key ] ?? '' ) ); }
				}
				$media = $values['featured_image'] ?? array();
				if ( ! empty( $media['attachment_id'] ) && (int) $media['site_id'] === $site ) { set_post_thumbnail( $post_id, $media['attachment_id'] ); }
				else { delete_post_meta( $post_id, '_thumbnail_id' ); }
				update_post_meta( $post_id, '_msc_override_count', count( $store->overrides( $id, $site ) ) );
				update_post_meta( $post_id, '_msc_baseline', $values );
				Database::check( $wpdb->update( Database::table( 'maps' ), array( 'post_id' => $post_id, 'version' => $r['version'] ), array( 'record_id' => $id, 'site_id' => $site ) ) );
				$store->index( $r, $site, $values ); clean_post_cache( $post_id );
				do_action( 'msc_materialized', $r['uuid'], $site, $post_id, (int) $r['version'] );
			} catch ( \Throwable $e ) { if ( ! empty( $post_id ) ) { clean_post_cache( $post_id ); } throw $e; }
			finally { self::$writing = false; restore_current_blog(); }
			} );
		} );
	}
	public static function capture( int $post_id, \WP_Post $post ): void {
		if ( self::$writing || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) || ! isset( Schema::all()[ $post->post_type ] ) || ! Schema::enabled( $post->post_type ) ) { return; }
		$store = Plugin::store(); $r = $store->by_post( $post_id ); $schema = Schema::get( $post->post_type ); $values = array();
		foreach ( $schema['fields'] as $key => $field ) { if ( isset( $field['property'] ) ) { $values[ $key ] = $post->{$field['property']}; } }
		try {
			if ( ! $r ) {
				$r = $store->create( $post->post_type, $values ); global $wpdb;
				Database::check( $wpdb->insert( Database::table( 'maps' ), array( 'record_id' => $r['id'], 'site_id' => get_current_blog_id(), 'post_id' => $post_id, 'status' => in_array( $post->post_status, array( 'publish', 'private' ), true ) ? $post->post_status : 'draft' ) ) );
				Plugin::materializer()->sync( (int) $r['id'], get_current_blog_id() );
			} else {
				$baseline = get_post_meta( $post_id, '_msc_baseline', true ) ?: array(); $changed = array();
				foreach ( $values as $key => $value ) { if ( (string) ( $baseline[ $key ] ?? '' ) !== (string) $value ) { $changed[ $key ] = $value; } }
				$m = $store->mapping( (int) $r['id'] );
				$status = $post->post_status !== ( 'unpublished' === $m['status'] ? 'draft' : $m['status'] ) ? $post->post_status : null;
				if ( $changed || $status ) { $store->local( $r['id'], $changed, array(), self::$native_versions[ $post_id ] ?? (int) $m['local_version'], $status ); }
			}
		} catch ( \Throwable $e ) {
			Admin::notice( $e->getMessage(), 'error' );
			if ( $r ) { Queue::sync( (int) $r['id'], get_current_blog_id() ); }
		} finally { unset( self::$native_versions[ $post_id ] ); }
	}
	public static function deleted( int $post_id ): void {
		if ( self::$writing ) { return; }
		$r = Plugin::store()->by_post( $post_id );
		if ( $r ) {
			global $wpdb;
			$wpdb->update( Database::table( 'maps' ), array( 'post_id' => null, 'suppressed' => 1, 'status' => 'removed', 'local_version' => (int) Plugin::store()->mapping( (int) $r['id'] )['local_version'] + 1 ), array( 'record_id' => $r['id'], 'site_id' => get_current_blog_id() ) );
			$wpdb->delete( Database::table( 'lookup' ), array( 'record_id' => $r['id'], 'site_id' => get_current_blog_id() ) );
		}
	}
	public static function trashed( int $post_id ): void {
		if ( self::$writing ) { return; } $r = Plugin::store()->by_post( $post_id );
		if ( $r ) { global $wpdb; $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::table( 'maps' ) . ' SET status=%s,local_version=local_version+1 WHERE record_id=%d AND site_id=%d', 'unpublished', $r['id'], get_current_blog_id() ) ); }
	}
}
