<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlockedManual extends Base {

	use RequestIP;

	public const SLUG = 'is_ip_blocked_manual';

	protected function execConditionCheck() :bool {
		return $this->con()->this_req->is_ip_blocked_shield_manual =
			( new IpRuleStatus( $this->getRequestIP() ) )->hasManualBlock();
	}
}