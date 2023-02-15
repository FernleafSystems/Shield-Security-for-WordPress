<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsIpBlockedCrowdsec extends Base {

	use RequestIP;

	public const SLUG = 'is_ip_blocked_crowdsec';

	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		$srvIP = Services::IP();

		$thisReq->is_ip_blocked_crowdsec =
			!$srvIP->checkIp( $this->getRequestIP(), $srvIP->getServerPublicIPs() )
			&& ( new IpRuleStatus( $this->getRequestIP() ) )
				->setMod( $this->getCon()->getModule_IPs() )
				->hasCrowdsecBlock();
		return $thisReq->is_ip_blocked_crowdsec;
	}
}