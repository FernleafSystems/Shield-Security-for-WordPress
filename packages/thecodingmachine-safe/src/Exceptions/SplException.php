<?php declare( strict_types=1 );

namespace Safe\Exceptions;

use Safe\Exceptions\Traits\CreatesFromPhpError;

class SplException extends \ErrorException implements SafeExceptionInterface {

	use CreatesFromPhpError;
}
