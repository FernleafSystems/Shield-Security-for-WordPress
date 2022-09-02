<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsIpBlockedCrowdsec extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked_crowdsec';

	/**
	 * @inheritDoc
	 */
	protected function execConditionCheck() :bool {
		$con = $this->getCon();
		$thisReq = $con->this_req;
		if ( !isset( $thisReq->is_ip_crowdsec_blocked ) && !empty( $this->getRequestIP() ) ) {
			$srvIP = Services::IP();
			$thisReq->is_ip_crowdsec_blocked =
				!$srvIP->checkIp( $this->getRequestIP(), $srvIP->getServerPublicIPs() )
				&& ( new IpRuleStatus( $this->getRequestIP() ) )
					->setMod( $this->getCon()->getModule_IPs() )
					->hasCrowdsecBlock();
		}
		return $thisReq->is_ip_crowdsec_blocked;
	}
}