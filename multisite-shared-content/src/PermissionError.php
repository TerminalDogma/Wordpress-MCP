<?php
/**
 * The current user lacks a capability, or the type is not enabled on this site.
 *
 * @package MultisiteSharedContent
 */

namespace MSC;

final class PermissionError extends HttpError {
	public function __construct( string $message ) {
		parent::__construct( $message, 403 );
	}
}
