<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\Wordpress\Services\Utilities\IpUtils;

class UnitTestIpUtils extends IpUtils {

	private ?\Closure $validator;

	public function __construct( ?\Closure $validator = null ) {
		$this->validator = $validator;
	}

	public function isValidIp( $ip, $flags = null ) {
		return $this->validator instanceof \Closure
			? (bool)( $this->validator )( (string)$ip )
			: \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false;
	}
}
