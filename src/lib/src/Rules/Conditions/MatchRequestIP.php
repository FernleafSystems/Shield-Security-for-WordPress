<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\IpsToMatchUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestIpUnavailableException;
use FernleafSystems\Wordpress\Services\Services;

class MatchRequestIP extends Base {

	use RequestIP;

	const SLUG = 'match_request_ip';

	protected function execConditionCheck() :bool {
		return $this->matchRequestIP();
	}

	/**
	 * @throws IpsToMatchUnavailableException
	 * @throws RequestIpUnavailableException
	 */
	protected function matchRequestIP() :bool {
		if ( empty( $this->match_ips ) ) {
			throw new IpsToMatchUnavailableException();
		}
		return Services::IP()->checkIp( $this->getRequestIP(), $this->match_ips );
	}
}