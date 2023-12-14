<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	IpsToMatchUnavailableException,
	RequestIpUnavailableException
};
use FernleafSystems\Wordpress\Services\Exceptions\NotAnIpAddressOrRangeException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $match_ip
 */
class MatchRequestIpAddress extends Base {

	use Traits\RequestIP;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_address';

	public function getDescription() :string {
		return __( 'Does the current request originate from a given set of IP Addresses.', 'wp-simple-firewall' );
	}

	/**
	 * @throws IpsToMatchUnavailableException
	 * @throws RequestIpUnavailableException
	 */
	protected function execConditionCheck() :bool {
		if ( empty( $this->match_ip ) ) {
			throw new IpsToMatchUnavailableException();
		}

		try {
			$in = Services::IP()->IpIn( $this->getRequestIP(), [ $this->match_ip ] );
		}
		catch ( NotAnIpAddressOrRangeException $e ) {
			$in = false;
		}
		return $in;
	}

	public function getParamsDef() :array {
		return [
			'match_ip' => [
				'type'  => 'string',
				'label' => __( 'IP Address To Match', 'wp-simple-firewall' ),
			],
		];
	}
}