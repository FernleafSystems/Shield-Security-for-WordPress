<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsIpCrowdsec extends Base {

	use RequestIP;

	const SLUG = 'is_ip_crowdsec';

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
				&& $con->getModule_IPs()->getCrowdSecCon()->isIpOnCrowdSec( $this->getRequestIP() );
		}
		return $thisReq->is_ip_crowdsec_blocked;
	}
}