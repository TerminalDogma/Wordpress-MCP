<?php
namespace MSC;

/** Reuses reviewed snapshots and the existing resumable import progress UI. */
final class ComicImport {
    public static function parent( string $uuid, string $source = '' ): array {
        $r = Plugin::store()->get( $uuid );
        if ( ! $r || 'msc_comic' !== $r['type'] || 'active' !== $r['state'] || ! Schema::enabled( 'msc_comic' ) || $source && (string) ( $r['data']['external_ids']['gcd'] ?? '' ) !== $source ) { throw new SourceError( 'Import or select the matching Comic Book before adding its issues.' ); } return $r;
    }
    public static function connect( array &$snapshot, string $parent, ?array $existing ): void {
        $source = $snapshot['details']['parent_gcd'] ?? '';
        if ( ! $parent && $existing ) { $parent = $existing['data']['parent_comic'] ?? ''; }
        if ( ! $parent ) { $found = OpenImport::matches( 'msc_comic', array( 'gcd' => $source ) ); if ( 1 === count( $found ) ) { $parent = $found[0]['uuid']; } }
        $owner = self::parent( $parent, $source );
        if ( $existing && ! empty( $existing['data']['parent_comic'] ) && $existing['data']['parent_comic'] !== $owner['uuid'] ) { throw new SourceError( 'This issue belongs to another Comic Book. Review its parent first.', 409 ); }
        $snapshot['fields']['parent_comic'] = $owner['uuid'];
    }
    public static function start( string $parent, $ids, bool $refresh ): string {
        if ( ! Schema::enabled( 'msc_comic_issue' ) || ! OpenImport::allowed() ) { throw new SourceError( 'Comic import permission is required.', 403 ); }
        $r = self::parent( $parent ); $source = (string) ( $r['data']['external_ids']['gcd'] ?? '' );
        if ( ! is_array( $ids ) || ! array_is_list( $ids ) || ! $ids || count( $ids ) > 50 || array_filter( $ids, static fn( $id ) => ! is_string( $id ) ) ) { throw new SourceError( 'Select between 1 and 50 issues from this page.' ); }
        $available = array_column( ComicSource::issues( $source ), null, 'id' ); $items = array();
        foreach ( array_unique( $ids ) as $id ) {
            if ( ! isset( $available[ $id ] ) ) { throw new SourceError( 'A selected issue does not belong to this Comic Book.' ); }
            $matches = OpenImport::matches( 'msc_comic_issue', array( 'gcd' => $id ) );
            if ( count( $matches ) > 1 ) { throw new SourceError( 'Duplicate GCD issue identities need review.', 409 ); }
            if ( $matches && ! $refresh ) { continue; }
            Permissions::require( $matches ? 'msc_edit_global' : 'msc_create_global', 'msc_comic_issue' );
            $items[] = array( 'id' => $id, 'title' => $available[ $id ]['title'], 'target' => $matches[0]['uuid'] ?? '', 'version' => (int) ( $matches[0]['version'] ?? 0 ) );
        }
        if ( ! $items ) { throw new SourceError( 'Selected issues are already imported. Enable refreshing existing issues to update them.' ); }
        $token = wp_generate_uuid4(); $job = array( 'comic' => true, 'owner' => array( get_current_network_id(), get_current_blog_id(), get_current_user_id() ), 'parent' => $parent, 'show_id' => $source, 'items' => $items, 'index' => 0, 'results' => array() ); set_site_transient( 'msc_open_job_' . $token, $job, DAY_IN_SECONDS ); return $token;
    }
    public static function step( string $token ): array {
        return Database::lock( 'open:job:' . $token, static function () use ( $token ) {
            $job = OpenImport::batch( $token ); self::parent( $job['parent'], $job['show_id'] );
            if ( ! Schema::enabled( 'msc_comic_issue' ) || ! OpenImport::allowed() ) { throw new SourceError( 'Comic import permission is required.', 403 ); }
            if ( $job['index'] >= count( $job['items'] ) ) { return $job; } $item = $job['items'][ $job['index'] ];
            try {
                if ( $item['target'] ) { $current = Plugin::store()->get( $item['target'] ); if ( ! $current || (int) $current['version'] !== $item['version'] ) { throw new SourceError( 'This issue changed after batch selection. Preview it individually.', 409 ); } }
                $preview = OpenImport::preview( 'gcd', $item['id'], 'msc_comic_issue', $item['target'], $job['parent'] );
                if ( $item['target'] && $preview['version'] !== $item['version'] ) { throw new SourceError( 'This issue changed during the batch.', 409 ); }
                if ( ! $item['target'] && $preview['target'] ) { $job['results'][] = array( 'title' => $item['title'], 'status' => 'Skipped: already imported', 'uuid' => $preview['target'] ); }
                else { $saved = OpenImport::import( array( 'nonce' => wp_create_nonce( 'msc_open_import' ), 'token' => $preview['token'], 'fields' => array_keys( $preview['fields'] ) ) ); $job['results'][] = array( 'title' => $item['title'], 'status' => $item['target'] ? 'Updated' : 'Imported', 'uuid' => $saved['record']['uuid'], 'warnings' => $saved['warnings'] ); }
            } catch ( \Throwable $e ) {
                if ( $e instanceof SourceError && in_array( $e->status, array( 429, 502, 503 ), true ) ) { throw $e; }
                $job['results'][] = array( 'title' => $item['title'], 'status' => 'Failed: ' . $e->getMessage() );
            }
            ++$job['index']; set_site_transient( 'msc_open_job_' . $token, $job, DAY_IN_SECONDS ); return $job;
        } );
    }
}
