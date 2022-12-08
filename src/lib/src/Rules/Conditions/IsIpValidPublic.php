<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsIpValidPublic extends Base {

	use RequestIP;

	public const SLUG = 'is_ip_valid_public';

	protected function execConditionCheck() :bool {
		$ip = $this->getRequestIP();
		return !empty( $ip ) && Services::IP()->isValidIp_PublicRemote( $ip );
	}
}