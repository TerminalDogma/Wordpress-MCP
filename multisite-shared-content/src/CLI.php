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
	/** Print queue totals. */
	public function status(): void { \WP_CLI::log( wp_json_encode( Queue::status() ) ); }
	/** Retry failed jobs (run as a network administrator). */
	public function retry(): void { Queue::retry(); \WP_CLI::success( 'Failed jobs requeued.' ); }
}
