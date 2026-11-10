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
    /** Import the bundled 250 Movies and 250 TV Shows as drafts. */
    public function training(): void {
        $token = TrainingCatalog::start();
        do { $job = TrainingCatalog::step( $token ); \WP_CLI::log( $job['index'] . ' / 500 processed' ); } while ( $job['index'] < 500 );
        $counts = array_count_values( array_column( $job['results'], 'status' ) );
        \WP_CLI::log( wp_json_encode( $counts ) );
        foreach ( $job['results'] as $result ) { if ( str_starts_with( $result['status'], 'Needs review:' ) ) { \WP_CLI::warning( $result['title'] . ': ' . $result['status'] ); } }
        \WP_CLI::success( 'Training catalog processed. Publish reviewed records from the Movies and TV Shows post lists.' );
    }

	/** Print queue totals. */
	public function status(): void { \WP_CLI::log( wp_json_encode( Queue::status() ) ); }
	/** Retry failed jobs (run as a network administrator). */
	public function retry(): void { Queue::retry(); \WP_CLI::success( 'Failed jobs requeued.' ); }
}
