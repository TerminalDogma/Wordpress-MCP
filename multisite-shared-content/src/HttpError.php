<?php
/**
 * Base class for errors that carry an HTTP status for REST and admin responses.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

class HttpError extends \RuntimeException {
	public function __construct( string $message, public readonly int $status = 400 ) {
		parent::__construct( $message );
	}
}
