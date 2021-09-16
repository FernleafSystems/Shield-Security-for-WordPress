<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield;

class RateLimitExceededException extends \Exception {

	private $requestCount = 0;

	public function __construct( string $message = "", int $requestCount = 0 ) {
		$this->requestCount = $requestCount;
		parent::__construct( $message );
	}

	public function getCount() :int {
		return $this->requestCount;
	}
}