<?php declare( strict_types=1 );

namespace Safe\Exceptions;

use Safe\Exceptions\Traits\CreatesFromPhpError;

class UrlException extends \ErrorException implements SafeExceptionInterface {

	use CreatesFromPhpError;
}
