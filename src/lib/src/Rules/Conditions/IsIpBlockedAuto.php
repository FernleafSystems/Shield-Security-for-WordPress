<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpBlockedAuto extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_blocked_auto';

	protected function execConditionCheck() :bool {
		/** Note: Don't be tempted to set the flag on $this_req for auto block as we must first consider High Reputation */
		return ( new IpRuleStatus( $this->getRequestIP() ) )->hasAutoBlock();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_ip_blocked_shield_auto;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_blocked_shield_auto = $result;
	}
}