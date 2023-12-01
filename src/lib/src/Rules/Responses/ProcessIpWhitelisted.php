<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class ProcessIpWhitelisted extends Base {

	public const SLUG = 'process_ip_whitelisted';

	public function execResponse() :bool {
		$con = self::con();

		$ipRecord = \current( ( new IpRuleStatus( $con->this_req->ip ) )->getRulesForBypass() );
		if ( !empty( $ipRecord ) ) {
			/** @var Update $updater */
			$updater = $con->getModule_IPs()->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $ipRecord );
		}

		return true;
	}
}