<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $request_useragent
 */
trait UserAgent {

	/**
	 * @throws RequestUseragentUnavailableException
	 */
	protected function getUserAgent() :string {
		$value = $this->request_useragent;
		if ( empty( $value ) ) {
			$value = Services::Request()->getUserAgent();
		}
		if ( empty( $value ) ) {
			throw new RequestUseragentUnavailableException();
		}
		return $value;
	}
}