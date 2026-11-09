<?php
/**
 * A record changed since the caller read it; the caller should reload and retry.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class ConflictError extends HttpError {
	public function __construct( string $message ) {
		parent::__construct( $message, 409 );
	}
}
