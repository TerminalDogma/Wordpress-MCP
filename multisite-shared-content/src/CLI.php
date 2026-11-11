<?php
namespace MSC;
final class CLI {
	/** Configure the four network presentation sites and their default content types once. */
	public function sites(): void { NetworkSites::setup(); \WP_CLI::success( 'Four site identities, content-type profiles and release calendars are ready.' ); }
	/** One-time Site A catch-up. Promotes source overrides and resets conflicting destination overrides. */
	public function reconcile(): void { \WP_CLI::success( 'Site A catch-up: ' . wp_json_encode( SiteAUpdate::run() ) ); }
	/** Process durable jobs. ## OPTIONS
	 * [--limit=<number>]
	 * : Maximum jobs; runtime is capped at 20 seconds.
	 */
	public function queue( $args, $assoc ): void { \WP_CLI::log( wp_json_encode( Queue::run( (int) ( $assoc['limit'] ?? 100 ) ) ) ); }
    /** Rebuild complete training drafts, replacing untouched old stubs. ## OPTIONS
     * [--resume=<token>]
     * : Resume an interrupted import token printed by this command.
     */
    public function training( $args, $assoc ): void {
        $token = $assoc['resume'] ?? TrainingCatalog::start(); TrainingCatalog::job( $token );
        \WP_CLI::log( 'Import token: ' . $token . ' (resume with wp --user=1 msc training --resume=' . $token . ')' );
        do { $job = TrainingCatalog::step( $token ); \WP_CLI::log( $job['index'] . ' / 500 processed' ); if ( ! empty( $job['retry_after'] ) ) { \WP_CLI::log( 'Waiting for the source request window.' ); sleep( min( 60, (int) $job['retry_after'] ) ); } } while ( $job['index'] < 500 );
        $counts = array_count_values( array_column( $job['results'], 'status' ) ); \WP_CLI::log( wp_json_encode( $counts ) ); $failures = 0;
        foreach ( $job['results'] as $result ) { if ( str_starts_with( $result['status'], 'Needs review:' ) ) { ++$failures; \WP_CLI::warning( $result['title'] . ': ' . $result['status'] ); } }
        if ( $failures ) { \WP_CLI::error( $failures . ' titles remain incomplete. Run the command again to retry; completed and editorial records will be retained.' ); }
        \WP_CLI::success( 'Complete training records are ready for editorial review. Publish from the Movies and TV Shows post lists.' );
    }

	/** Print queue totals. */
	public function status(): void { \WP_CLI::log( wp_json_encode( Queue::status() ) ); }
	/** Retry failed jobs (run as a network administrator). */
	public function retry(): void { Queue::retry(); \WP_CLI::success( 'Failed jobs requeued.' ); }
}
