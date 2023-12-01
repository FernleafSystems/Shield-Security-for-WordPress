<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlockRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class ProcessIpBlockedShield extends Base {

	public const SLUG = 'process_ip_blocked_shield';

	public function execResponse() :bool {
		$ipStatus = new IpRuleStatus( self::con()->this_req->ip );

		if ( $ipStatus->hasManualBlock() ) {
			$IP = \current( $ipStatus->getRulesForManualBlock() );
		}
		elseif ( $ipStatus->hasAutoBlock() ) {
			$IP = $ipStatus->getRuleForAutoBlock();
		}

		if ( empty( $IP ) ) {
			error_log( 'SetIpBlockedShield: should never get here: '.var_export( self::con()->this_req->ip, true ) );
			throw new \Exception( 'SetIpBlockedShield: should never get here' );
		}

		/** @var Update $upd */
		$upd = self::con()
				   ->getModule_IPs()
				   ->getDbH_IPRules()
				   ->getQueryUpdater();
		$upd->updateLastAccessAt( $IP );

		add_action( 'init', function () {
			( new BlockRequest() )->execute();
		}, HookTimings::INIT_RULES_RESPONSE_IP_BLOCK_REQUEST_SHIELD );

		return true;
	}
}