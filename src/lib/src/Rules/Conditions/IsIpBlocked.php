<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsIpBlocked extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked';

	/**
	 * TODO: $con->fireEvent( 'not_conn_kill_high_rep' );
	 * @inheritDoc
	 */
	protected function execConditionCheck() :bool {
		$thisReq = $this->getCon()->this_req;
		if ( !isset( $thisReq->is_ip_blocked ) ) {
			$srvIP = Services::IP();
			$thisReq->is_ip_blocked = !$srvIP->checkIp( $this->getRequestIP(), $srvIP->getServerPublicIPs() )
									  && ( new IpRuleStatus( $this->getRequestIP() ) )
										  ->setMod( $this->getCon()->getModule_IPs() )
										  ->isBlockedByShield();
		}
		return $thisReq->is_ip_blocked;
	}
}