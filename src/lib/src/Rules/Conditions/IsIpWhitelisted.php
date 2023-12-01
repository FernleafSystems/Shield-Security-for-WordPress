<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IsIpWhitelisted extends Base {

	use Traits\RequestIP;

	public const SLUG = 'is_ip_whitelisted';

	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->getRequestIP() ) )->isBypass();
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_ip_whitelisted;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_ip_whitelisted = $result;
	}
}