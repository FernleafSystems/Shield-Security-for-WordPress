<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class SetIpBlocked extends Base {

	const SLUG = 'set_ip_blocked';

	protected function execResponse() :bool {
		$con = $this->getCon();
		$modIP = $this->getCon()->getModule_IPs();

		$con->this_req->is_ip_blocked = true;

		$ipStatus = ( new IpRuleStatus( $con->this_req->ip ) )->setMod( $modIP );
		if ( $ipStatus->hasManualBlock() ) {
			$IP = current( $ipStatus->getRulesForManualBlock() );
		}
		elseif ( $ipStatus->isAutoBlacklistedAndBlocked() ) {
			$IP = $ipStatus->getRuleForAutoBlock();
		}
		if ( empty( $IP ) ) {
			error_log( 'SetIpBlocked: should never get here' );
			throw new \Exception( 'SetIpBlocked: should never get here' );
		}
		/** @var Update $upd */
		$upd = $modIP->getDbH_IPRules()->getQueryUpdater();
		$upd->updateLastAccessAt( $IP );

		add_action( 'init', function () {
			( new BlockRequest() )
				->setMod( $this->getCon()->getModule_IPs() )
				->execute();
		}, -100000 );

		return true;
	}
}