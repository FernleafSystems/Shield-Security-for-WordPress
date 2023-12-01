<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpBlacklisted extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_blacklisted';

	protected function execConditionCheck() :bool {
		$status = new IpRuleStatus( $this->getRequestIP() );
		return $status->isBlockedByShield() || $status->isAutoBlacklisted();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_ip_blacklisted;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_blacklisted = $result;
	}
}