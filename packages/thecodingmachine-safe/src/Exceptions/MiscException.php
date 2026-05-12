<?php declare( strict_types=1 );

namespace Safe\Exceptions;

use Safe\Exceptions\Traits\CreatesFromPhpError;

class MiscException extends \ErrorException implements SafeExceptionInterface {

	use CreatesFromPhpError;
}
