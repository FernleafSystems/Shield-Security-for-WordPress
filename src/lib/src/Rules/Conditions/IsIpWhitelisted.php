<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpWhitelisted extends Base {

	use RequestIP;

	public const SLUG = 'is_ip_whitelisted';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		return $thisReq->is_ip_whitelisted ??
			   $thisReq->is_ip_whitelisted = ( new IpRuleStatus( $this->getRequestIP() ) )->isBypass();
	}
}