<?php
namespace MSC;
final class Relationships {
	/** Effective indexed episode query; global scope uses site=0. Dates use each episode's editorial calendar date. */
	public static function episodes( ?string $show = null, ?string $from = null, int $site = 0, int $limit = 100, bool $release_order = false ): array {
		global $wpdb; $l = Database::table( 'lookup' ); $r = Database::table( 'records' ); $args = array( $site, $site, $site, $site );
		$sql = "SELECT r.id FROM $r r LEFT JOIN $l p ON p.record_id=r.id AND p.site_id=%d AND p.field='parent_show' LEFT JOIN $l d ON d.record_id=r.id AND d.site_id=%d AND d.field='release_date' LEFT JOIN $l s ON s.record_id=r.id AND s.site_id=%d AND s.field='season_number' LEFT JOIN $l e ON e.record_id=r.id AND e.site_id=%d AND e.field='episode_number' WHERE r.type='msc_episode' AND r.state='active'";
		if ( $show ) { $sql .= ' AND p.text_value=%s'; $args[] = $show; }
		if ( $from ) { $sql .= ' AND d.text_value>=%s'; $args[] = $from; }
		if ( $site ) { $sql .= ' AND EXISTS (SELECT 1 FROM ' . Database::table( 'maps' ) . " m WHERE m.record_id=r.id AND m.site_id=%d AND m.status='publish' AND m.suppressed=0)"; $args[] = $site; }
		$sql .= ' ORDER BY ' . ( $release_order ? 'd.text_value,' : '' ) . 's.number_value,e.number_value,r.id LIMIT %d'; $args[] = min( 500, max( 1, $limit ) );
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, ...$args ) ) );
	}
	public static function seasons( string $show, int $site = 0 ): array {
		$groups = array();
		foreach ( self::episodes( $show, null, $site, 500 ) as $id ) {
			$r = $site ? Plugin::store()->resolved( $id, $site ) : Plugin::store()->get( $id );
			$groups[ $r['data']['season_number'] ?? 0 ][] = $r;
		}
		ksort( $groups, SORT_NUMERIC ); return $groups;
	}
}
