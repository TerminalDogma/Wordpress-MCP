<?php
namespace MSC;
final class Queue {
	private static function put( string $key, string $kind, int $record, int $site, string $type = '' ): void {
		global $wpdb;
		Database::check( $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . Database::table( 'jobs' ) . ' (job_key,kind,record_id,site_id,type,available_at,last_error) VALUES (%s,%s,%d,%d,%s,UTC_TIMESTAMP(),%s) ON DUPLICATE KEY UPDATE generation=generation+1,scan_after=0,attempts=0,state=%s,available_at=UTC_TIMESTAMP(),last_error=%s', $key, $kind, $record, $site, $type, '', 'pending', '' ) ) );
	}
	public static function fanout( int $record ): void {
		self::put( 'fanout:' . $record, 'fanout', $record, 0 );
	}
	public static function sync( int $record, int $site ): void {
		self::put( "sync:$record:$site", 'sync', $record, $site );
	}
	public static function seed( int $site, string $type ): void {
		self::put( "seed:$site:$type", 'seed', 0, $site, $type );
	}
	public static function run( int $limit = 20 ): array {
		return Database::lock(
			'worker',
			static function () use ( $limit ) {
				global $wpdb;
				$ok     = 0;
				$failed = 0;
				$table  = Database::table( 'jobs' );
				$start  = microtime( true );
				for ( $n = 0; $n < min( 5000, $limit ) && microtime( true ) - $start < 20; ++$n ) {
					$j = $wpdb->get_row( "SELECT * FROM $table WHERE state='pending' AND available_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 1", ARRAY_A );
					if ( ! $j ) {
						break;
					}
					try {
						$scan_after = self::execute( $j );
						if ( null === $scan_after ) {
							Database::check(
								$wpdb->delete(
									$table,
									array(
										'id'         => $j['id'],
										'generation' => $j['generation'],
									)
								)
							);
						} else {
							Database::check( $wpdb->query( $wpdb->prepare( "UPDATE $table SET scan_after=%d WHERE id=%d AND generation=%d", $scan_after, $j['id'], $j['generation'] ) ) );
						}
						++$ok;
					} catch ( \Throwable $e ) {
						$a = (int) $j['attempts'] + 1;
						$wpdb->query( $wpdb->prepare( "UPDATE $table SET attempts=%d,state=%s,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL %d SECOND),last_error=%s WHERE id=%d AND generation=%d", $a, $a >= 5 ? 'failed' : 'pending', min( 3600, 30 * ( 2 ** $a ) ), substr( sanitize_text_field( $e->getMessage() ), 0, 1000 ), $j['id'], $j['generation'] ) );
						++$failed;
					}
				}
				return array(
					'processed' => $ok,
					'errors'    => $failed,
					'status'    => self::status(),
				);
			}
		);
	}
	private static function execute( array $j ): ?int {
		global $wpdb;
		if ( 'sync' === $j['kind'] ) {
			Plugin::materializer()->sync( (int) $j['record_id'], (int) $j['site_id'] );
			return null;
		}
		if ( 'fanout' === $j['kind'] ) {
			$sites = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id=%d AND blog_id>%d AND deleted=0 AND archived=0 AND spam=0 ORDER BY blog_id LIMIT 100", get_current_network_id(), $j['scan_after'] ) );
			foreach ( $sites as $site ) {
				self::sync( (int) $j['record_id'], (int) $site );
			}
			return count( $sites ) === 100 ? (int) end( $sites ) : null;
		}
		if ( 'seed' === $j['kind'] ) {
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . ' WHERE type=%s AND id>%d ORDER BY id LIMIT 100', $j['type'], $j['scan_after'] ) );
			foreach ( $ids as $id ) {
				self::sync( (int) $id, (int) $j['site_id'] );
			}
			return count( $ids ) === 100 ? (int) end( $ids ) : null;
		}
		throw new \RuntimeException( 'Unknown job kind.' );
	}
	public static function status(): array {
		global $wpdb;
		$t = Database::table( 'jobs' );
		return array(
			'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE state='pending'" ),
			'failed'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE state='failed'" ),
		);
	}
	public static function retry(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			throw new \RuntimeException( 'Network permission required.' );
		}
		global $wpdb;
		$wpdb->query( 'UPDATE ' . Database::table( 'jobs' ) . " SET state='pending',attempts=0,available_at=UTC_TIMESTAMP() WHERE state='failed'" );
	}
}
