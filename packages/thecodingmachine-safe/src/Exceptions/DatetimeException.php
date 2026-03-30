<?php declare( strict_types=1 );

namespace Safe\Exceptions;

use Safe\Exceptions\Traits\CreatesFromPhpError;

class DatetimeException extends \ErrorException implements SafeExceptionInterface {

	use CreatesFromPhpError;
}
