<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlacklisted extends Base {

	use RequestIP;

	public const SLUG = 'is_ip_blacklisted';

	protected function execConditionCheck() :bool {
		$thisReq = $this->con()->this_req;
		if ( !isset( $thisReq->is_ip_blacklisted ) ) {
			$status = new IpRuleStatus( $this->getRequestIP() );
			$thisReq->is_ip_blacklisted = $status->isBlockedByShield() || $status->isAutoBlacklisted();
		}
		return $thisReq->is_ip_blacklisted;
	}
}