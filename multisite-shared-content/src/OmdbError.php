<?php
namespace MSC;

final class OmdbError extends \RuntimeException {
	public function __construct( string $message, public readonly int $status = 400 ) {
		parent::__construct( $message );
	}
}
