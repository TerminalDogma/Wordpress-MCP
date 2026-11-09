<?php
/**
 * The requested record, revision or site does not exist.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class NotFoundError extends HttpError {
	public function __construct( string $message ) {
		parent::__construct( $message, 404 );
	}
}
