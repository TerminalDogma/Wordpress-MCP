<?php
namespace MSC;

/** Explicit one-time update policy: Site A's effective shared fields win. */
final class SiteAUpdate {
    public const MARKER = 'msc_site_a_reconcile_180';
    public static function apply( array $record, int $source ): array {
        $site = get_site( $source ); if ( ! $site || (int) $site->network_id !== get_current_network_id() ) { throw new \RuntimeException( 'Invalid Site A source.' ); }
        switch_to_blog( $source ); try { return self::apply_current( $record, $source ); } finally { restore_current_blog(); }
    }
    private static function apply_current( array $record, int $source ): array {
        Permissions::require( 'msc_edit_global', $record['type'] ); if ( ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Network administrator permission required.' ); }
        $effective = Plugin::store()->resolved( $record['uuid'], $source ); $fields = Schema::get( $record['type'] )['fields']; $values = array_intersect_key( $effective['overrides'], $fields );
        $sites = get_sites( array( 'network_id' => get_current_network_id(), 'number' => 0, 'deleted' => 0, 'archived' => 0, 'spam' => 0 ) ); $before = array();
        foreach ( $sites as $site ) { $id = (int) $site->blog_id; if ( Schema::enabled( $record['type'], $id ) ) { $before[ $id ] = Plugin::store()->overrides( (int) $record['id'], $id ); } }
        // Keep the first snapshot even when a partially completed update is resumed.
        $backup_key = self::MARKER . '_backup_' . $record['id'];
        add_network_option( get_current_network_id(), $backup_key, array( 'at' => gmdate( 'c' ), 'source' => $source, 'global' => $record, 'overrides' => $before ) );
        if ( false === get_network_option( get_current_network_id(), $backup_key, false ) ) { throw new \RuntimeException( 'Could not save the recovery snapshot. Catch-up stopped before changing this record.' ); }
        $saved = Database::transaction( static function () use ( $record, $source, $effective, $fields, $values, $sites ) {
            $r = $values ? Plugin::store()->update( $record['uuid'], $values, (int) $record['version'] ) : $record;
            $current_source = Plugin::store()->mapping( (int) $record['id'], $source ); if ( (int) ( $current_source['local_version'] ?? 0 ) !== (int) ( $effective['mapping']['local_version'] ?? 0 ) ) { throw new \RuntimeException( 'Conflict: Site A changed during catch-up. Run setup again.' ); }
            foreach ( $sites as $site ) {
                $id = (int) $site->blog_id; if ( ! Schema::enabled( $r['type'], $id ) ) { continue; }
                $local = Plugin::store()->resolved( $r['uuid'], $id ); $reset = array();
                foreach ( $local['overrides'] as $key => $value ) { if ( isset( $fields[ $key ] ) && false !== ( $fields[ $key ]['override'] ?? true ) && ( $id === $source || array_key_exists( $key, $effective['data'] ) && $value !== $effective['data'][ $key ] ) ) { $reset[] = $key; } }
                if ( $reset ) { switch_to_blog( $id ); try { Plugin::store()->local( $r['uuid'], array(), $reset, (int) $local['mapping']['local_version'] ); } finally { restore_current_blog(); } }
            } return $r;
        } ); Queue::fanout( (int) $saved['id'] ); return $saved;
    }
    public static function run(): array {
        if ( ! current_user_can( 'manage_network_options' ) ) { throw new \RuntimeException( 'Run this update as a network administrator.' ); }
        return Database::lock( self::MARKER, static function () {
            $state = get_network_option( get_current_network_id(), self::MARKER, array( 'after' => 0, 'processed' => 0 ) ); if ( ! empty( $state['complete'] ) ) { return $state; }
            $matches = get_sites( array( 'network_id' => get_current_network_id(), 'path' => '/site-a/', 'number' => 2 ) );
            if ( 1 !== count( $matches ) ) { throw new \RuntimeException( 'Could not identify one /site-a/ source site. No catch-up was performed.' ); } $source = (int) $matches[0]->blog_id;
            global $wpdb;
            do {
                $ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . Database::table( 'records' ) . " WHERE id>%d AND state='active' ORDER BY id LIMIT 100", $state['after'] ) );
                foreach ( $ids as $id ) {
                    $r = Plugin::store()->get( (int) $id );
                    if ( Schema::enabled( $r['type'], $source ) ) { self::apply( $r, $source ); ++$state['processed']; }
                    $state['after'] = (int) $id; $state['source'] = $source; update_network_option( get_current_network_id(), self::MARKER, $state );
                }
            } while ( count( $ids ) === 100 );
            $state['complete'] = true; $state['at'] = gmdate( 'c' ); update_network_option( get_current_network_id(), self::MARKER, $state ); return $state;
        } );
    }
}
