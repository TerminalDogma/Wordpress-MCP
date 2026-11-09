<?php
namespace MSC;

/** Immediate small-network delivery after editor saves, with durable queue fallback. */
final class Propagation {
	public static function boot(): void {
		add_action( 'admin_post_msc_propagate', array( self::class, 'handle' ) );
	}
	public static function send( array $record, int $site_id = 0 ): void {
		Queue::fanout( (int) $record['id'] );
		$args = array(
			'network_id' => get_current_network_id(),
			'number'     => 20,
			'deleted'    => 0,
			'archived'   => 0,
			'spam'       => 0,
		);
		if ( $site_id ) {
			$args['site__in'] = array( $site_id );
		}
		$start = microtime( true );
		foreach ( get_sites( $args ) as $site ) {
			if ( microtime( true ) - $start >= 5 ) {
				break;
			}
			$id = (int) $site->blog_id;
			if ( ! Schema::enabled( $record['type'], $id ) && ! Plugin::store()->mapping( (int) $record['id'], $id ) ) {
				continue;
			}
			try {
				Plugin::materializer()->sync( (int) $record['id'], $id );
			} catch ( \Throwable $e ) {
				Queue::sync( (int) $record['id'], $id );
				global $wpdb;
				$wpdb->update( Database::table( 'jobs' ), array( 'last_error' => substr( sanitize_text_field( $e->getMessage() ), 0, 1000 ) ), array( 'job_key' => 'sync:' . $record['id'] . ':' . $id ) );
			}
		}
	}
	public static function status( array $record ): array {
		global $wpdb;
		$rows   = array();
		$errors = $wpdb->get_results( $wpdb->prepare( 'SELECT site_id,last_error FROM ' . Database::table( 'jobs' ) . " WHERE record_id=%d AND kind='sync' AND last_error<>''", $record['id'] ), OBJECT_K );
		foreach ( get_sites(
			array(
				'network_id' => get_current_network_id(),
				'number'     => 50,
				'offset'     => isset( $_GET['msc_sites_offset'] ) && is_scalar( $_GET['msc_sites_offset'] ) ? absint( $_GET['msc_sites_offset'] ) : 0,
				'deleted'    => 0,
				'archived'   => 0,
				'spam'       => 0,
			)
		) as $site ) {
			$id        = (int) $site->blog_id;
			$m         = Plugin::store()->mapping( (int) $record['id'], $id );
			$overrides = Plugin::store()->overrides( (int) $record['id'], $id );
			$enabled   = Schema::enabled( $record['type'], $id );
			$state     = ! $enabled ? 'Not participating' : ( ! empty( $m['suppressed'] ) ? 'Removed on this site' : ( $m && $m['post_id'] && (int) $m['version'] >= (int) $record['version'] ? 'Up to date' : 'Pending' ) );
			if ( 'Pending' === $state && isset( $errors[ $id ] ) ) {
				$state = 'Retry pending: ' . $errors[ $id ]->last_error;
			}
			$rows[] = array(
				'id'          => $id,
				'label'       => get_blog_option( $id, 'blogname' ) . ' (' . $site->path . ')',
				'state'       => $state,
				'version'     => (int) ( $m['version'] ?? 0 ),
				'overrides'   => array_keys( $overrides ),
				'publication' => $m['status'] ?? 'Not created',
				'enabled'     => $enabled,
			);
		}
		return $rows;
	}
	public static function render( array $record ): void {
		if ( ! current_user_can( 'msc_edit_global' ) ) {
			return;
		}
		$rows = self::status( $record );
		echo '<section class="msc-propagation"><h2>Site synchronization</h2><p>Global revision ' . esc_html( $record['version'] ) . '. Participating sites receive global fields; local overrides and publication choices remain in effect. Save attempts immediate delivery, with queued retries for remaining sites.</p><table class="widefat striped"><thead><tr><th>Site</th><th>Delivery</th><th>Received revision</th><th>Local overrides</th><th>Publication</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr data-site="' . $row['id'] . '"><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['state'] ) . '</td><td>' . $row['version'] . '</td><td>' . esc_html( $row['overrides'] ? implode( ', ', $row['overrides'] ) : 'None — inherits global fields' ) . '</td><td>' . esc_html( $row['publication'] ) . '</td><td>';
			if ( $row['enabled'] && 'Removed on this site' !== $row['state'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="msc_propagate"><input type="hidden" name="record" value="' . esc_attr( $record['uuid'] ) . '"><input type="hidden" name="site" value="' . $row['id'] . '">';
				wp_nonce_field( 'msc_propagate_' . $record['uuid'] );
				echo '<button class="button">Sync this site</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table><p>To resume a local field’s inheritance, switch to local overrides on that site and clear its Override checkbox. Enable missing site participation in Network Admin → Shared Content.</p>';
		$offset = isset( $_GET['msc_sites_offset'] ) && is_scalar( $_GET['msc_sites_offset'] ) ? absint( $_GET['msc_sites_offset'] ) : 0;
		if ( count( $rows ) === 50 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'msc_sites_offset', $offset + 50 ) ) . '">Next 50 sites</a>';
		}
		echo '</section>';
	}
	public static function handle(): void {
		$uuid = isset( $_POST['record'] ) && is_string( $_POST['record'] ) ? sanitize_text_field( wp_unslash( $_POST['record'] ) ) : '';
		check_admin_referer( 'msc_propagate_' . $uuid );
		$r = Plugin::store()->get( $uuid );
		if ( ! $r || ! current_user_can( 'msc_edit_global' ) || ! Schema::enabled( $r['type'] ) ) {
			wp_die( 'Global editing permission is required.', 'Synchronization', array( 'response' => 403 ) );
		}
		self::send( $r, isset( $_POST['site'] ) && is_scalar( $_POST['site'] ) ? absint( $_POST['site'] ) : 0 );
		wp_safe_redirect( admin_url( 'admin.php?page=msc&mode=global&record=' . $uuid ) );
		exit;
	}
}
