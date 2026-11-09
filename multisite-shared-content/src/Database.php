<?php
namespace MSC;

final class Database {
	private static int $transaction_depth = 0;
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->base_prefix . 'msc_' . get_current_network_id() . '_' . $name;
	}
	public static function check( $result ) {
		global $wpdb;
		if ( false === $result ) {
			throw new \RuntimeException( 'MSC database operation failed: ' . $wpdb->last_error );
		}
		return $result;
	}
	public static function transaction( callable $callback ) {
		global $wpdb;
		$depth     = self::$transaction_depth;
		$savepoint = 'msc_transaction_' . $depth;
		self::check( $wpdb->query( $depth ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION' ) );
		++self::$transaction_depth;
		try {
			$result = $callback();
			self::check( $wpdb->query( $depth ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT' ) );
			return $result;
		} catch ( \Throwable $e ) {
			$wpdb->query( $depth ? 'ROLLBACK TO SAVEPOINT ' . $savepoint : 'ROLLBACK' );
			if ( $depth ) {
				$wpdb->query( 'RELEASE SAVEPOINT ' . $savepoint );
			}
			throw $e;
		} finally {
			--self::$transaction_depth;
		}
	}
	/**
	 * Run a callback while holding a named database lock.
	 *
	 * @param string   $name     Lock name, scoped to this network.
	 * @param callable $callback Work to run while the lock is held.
	 * @param int      $wait     Seconds to wait for the lock; 0 fails at once.
	 * @throws BusyError When another request holds the lock.
	 */
	public static function lock( string $name, callable $callback, int $wait = 5 ) {
		global $wpdb;
		$key = 'msc:' . md5( self::table( $name ) );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, max( 0, $wait ) ) ) ) {
			throw new BusyError();
		}
		try {
			return $callback();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}
	public static function migrate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c   = $wpdb->get_charset_collate();
		$r   = self::table( 'records' );
		$o   = self::table( 'overrides' );
		$m   = self::table( 'maps' );
		$v   = self::table( 'revisions' );
		$j   = self::table( 'jobs' );
		$i   = self::table( 'lookup' );
		$sql = array(
			"CREATE TABLE $r (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 uuid char(36) NOT NULL,
 type varchar(20) NOT NULL,
 data longtext NOT NULL,
 state varchar(12) NOT NULL DEFAULT 'active',
 version bigint unsigned NOT NULL DEFAULT 1,
 author bigint unsigned NOT NULL,
 updated_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY uuid (uuid),
 KEY type_state (type,state,id)
) ENGINE=InnoDB $c;",
			"CREATE TABLE $o (
 record_id bigint unsigned NOT NULL,
 site_id bigint unsigned NOT NULL,
 field varchar(64) NOT NULL,
 value longtext NOT NULL,
 PRIMARY KEY  (record_id,site_id,field),
 KEY site (site_id,record_id)
) ENGINE=InnoDB $c;",
			"CREATE TABLE $m (
 record_id bigint unsigned NOT NULL,
 site_id bigint unsigned NOT NULL,
 post_id bigint unsigned DEFAULT NULL,
 version bigint unsigned NOT NULL DEFAULT 0,
 local_version bigint unsigned NOT NULL DEFAULT 1,
 status varchar(12) NOT NULL DEFAULT 'draft',
 suppressed tinyint NOT NULL DEFAULT 0,
 PRIMARY KEY  (record_id,site_id),
 UNIQUE KEY local_post (site_id,post_id)
) ENGINE=InnoDB $c;",
			"CREATE TABLE $v (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 record_id bigint unsigned NOT NULL,
 version bigint unsigned NOT NULL,
 actor bigint unsigned NOT NULL,
 site_id bigint unsigned NOT NULL,
 snapshot longtext NOT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY revision (record_id,version)
) ENGINE=InnoDB $c;",
			"CREATE TABLE $j (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 job_key varchar(100) NOT NULL,
 kind varchar(12) NOT NULL,
 record_id bigint unsigned NOT NULL DEFAULT 0,
 site_id bigint unsigned NOT NULL DEFAULT 0,
 type varchar(20) NOT NULL DEFAULT '',
 scan_after bigint unsigned NOT NULL DEFAULT 0,
 generation bigint unsigned NOT NULL DEFAULT 1,
 attempts int NOT NULL DEFAULT 0,
 state varchar(12) NOT NULL DEFAULT 'pending',
 available_at datetime NOT NULL,
 last_error text NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY job_key (job_key),
 KEY runnable (state,available_at,id)
) ENGINE=InnoDB $c;",
			"CREATE TABLE $i (
 record_id bigint unsigned NOT NULL,
 site_id bigint unsigned NOT NULL,
 field varchar(64) NOT NULL,
 text_value varchar(191) NOT NULL DEFAULT '',
 number_value decimal(20,6) DEFAULT NULL,
 PRIMARY KEY  (record_id,site_id,field),
 KEY text_query (site_id,field,text_value,record_id),
 KEY number_query (site_id,field,number_value,record_id)
) ENGINE=InnoDB $c;",
		);
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		foreach ( array( $r, $o, $m, $v, $j, $i ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
				throw new \RuntimeException( 'Could not initialize MSC tables.' );
			}
		}
		update_network_option( get_current_network_id(), 'msc_db_version', 1 );
	}
}
