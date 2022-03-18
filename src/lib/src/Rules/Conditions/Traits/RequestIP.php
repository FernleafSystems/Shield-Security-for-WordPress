<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestIpUnavailableException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $request_ip
 */
trait RequestIP {

	/**
	 * @throws RequestIpUnavailableException
	 */
	protected function getRequestIP() :string {
		$value = $this->request_ip;
		if ( empty( $value ) ) {
			$value = Services::IP()->getRequestIp();
		}
		if ( empty( $value ) ) {
			throw new RequestIpUnavailableException();
		}
		return $value;
	}
}