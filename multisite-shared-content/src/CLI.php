<?php
namespace MSC;
final class CLI {
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
