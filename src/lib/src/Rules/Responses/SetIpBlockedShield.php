<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class SetIpBlockedShield extends Base {

	const SLUG = 'set_ip_blocked_shield';

	protected function execResponse() :bool {
		$con = $this->getCon();
		$modIP = $this->getCon()->getModule_IPs();

		$con->this_req->is_ip_blocked_shield = true;

		$ipStatus = ( new IpRuleStatus( $con->this_req->ip ) )->setMod( $modIP );
		if ( $ipStatus->hasManualBlock() ) {
			$con->this_req->is_ip_blocked_shield_manual = true;
			$IP = current( $ipStatus->getRulesForManualBlock() );
		}
		elseif ( $ipStatus->hasAutoBlock() ) {
			$con->this_req->is_ip_blocked_shield_auto = true;
			$IP = $ipStatus->getRuleForAutoBlock();
		}
		if ( empty( $IP ) ) {
			error_log( 'SetIpBlocked: should never get here: '.var_export( $con->this_req->ip, true ) );
			throw new \Exception( 'SetIpBlocked: should never get here' );
		}
		/** @var Update $upd */
		$upd = $modIP->getDbH_IPRules()->getQueryUpdater();
		$upd->updateLastAccessAt( $IP );

		add_action( 'init', function () {
			( new BlockRequest() )
				->setMod( $this->getCon()->getModule_IPs() )
				->execute();
		}, 0 );

		return true;
	}
}