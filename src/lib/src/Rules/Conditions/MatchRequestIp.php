<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Exceptions\NotAnIpAddressOrRangeException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	IpsToMatchUnavailableException,
	RequestIpUnavailableException
};
use FernleafSystems\Wordpress\Services\Services;

class MatchRequestIp extends Base {

	use Traits\RequestIP;

	public const SLUG = 'match_request_ip';

	/**
	 * @throws IpsToMatchUnavailableException
	 * @throws RequestIpUnavailableException
	 */
	protected function execConditionCheck() :bool {
		if ( empty( $this->match_ips ) ) {
			throw new IpsToMatchUnavailableException();
		}
		try {
			$in = Services::IP()->IpIn( $this->getRequestIP(), $this->match_ips );
		}
		catch ( NotAnIpAddressOrRangeException $e ) {
			$in = false;
		}
		return $in;
	}
}