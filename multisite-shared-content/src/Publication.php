<?php
namespace MSC;

/** Explicit publication actions preserve each site's effective fields and exclusions. */
final class Publication {
	public static function global_allowed(): bool {
		return current_user_can( 'manage_network_options' ) && current_user_can( 'msc_edit_global' ) && current_user_can( 'msc_publish_local' );
	}
	public static function require( string $scope, array $record ): void {
		if ( ! in_array( $scope, array( 'site', 'global' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid publication action.' );
		}
		Permissions::require( 'msc_publish_local', $record['type'] );
		if ( 'global' === $scope && ! self::global_allowed() ) {
			throw new \RuntimeException( 'Network administrator permission is required to publish globally.' );
		}
		if ( 'active' !== $record['state'] ) {
			throw new \InvalidArgumentException( 'Restore the shared record before publishing it.' );
		}
	}
	public static function publish( int $id, string $scope, int $local_version ): array {
		$store = Plugin::store();
		$r     = $store->get( $id );
		if ( ! $r ) {
			throw new \InvalidArgumentException( 'Record not found.' );
		}
		self::require( $scope, $r );
		return Database::transaction(
			static function () use ( $store, $r, $id, $scope, $local_version ) {
				$current = get_current_blog_id();
				$m       = $store->mapping( $id, $current );
				if ( ! $m || (int) $m['local_version'] !== $local_version ) {
					throw new ConflictError( 'Conflict: local publication changed. Reload before publishing.' );
				}
				$sites   = 'site' === $scope ? array( get_site( $current ) ) : get_sites(
					array(
						'network_id' => get_current_network_id(),
						'number'     => 0,
						'deleted'    => 0,
						'spam'       => 0,
						'archived'   => 0,
						'orderby'    => 'id',
						'order'      => 'ASC',
					)
				);
				$targets = array();
				foreach ( $sites as $site ) {
					$site_id  = (int) $site->blog_id;
					$resolved = $store->resolved( $id, $site_id );
					if ( ! Schema::enabled( $r['type'], $site_id ) || ! GamePolicy::accepts( $resolved, $site_id ) || ! empty( $resolved['mapping']['suppressed'] ) ) {
						continue;
					}
					switch_to_blog( $site_id );
					try {
								Permissions::require( 'msc_publish_local', $r['type'] );
					} finally {
							restore_current_blog();
					}
					$targets[] = $site_id;
				}
				if ( ! $targets ) {
					throw new \InvalidArgumentException( 'No participating sites can publish this record. Check content types, platform rules and local removals.' );
				}
				foreach ( $targets as $site_id ) {
					Plugin::materializer()->sync( $id, $site_id );
					switch_to_blog( $site_id );
					try {
						$m = $store->mapping( $id );
						$store->local( $id, array(), array(), (int) $m['local_version'], 'publish' );
					} finally {
						restore_current_blog();
					}
				}
				return array(
					'scope' => $scope,
					'sites' => $targets,
				);
			}
		);
	}
}
