<?php
/**
 * A lock is held by another request; the same request can be retried shortly.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class BusyError extends HttpError {
	public function __construct( string $message = 'Resource busy; please retry.' ) {
		parent::__construct( $message, 503 );
	}
}
