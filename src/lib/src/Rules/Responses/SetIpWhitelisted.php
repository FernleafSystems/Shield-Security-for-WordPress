<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class SetIpWhitelisted extends Base {

	const SLUG = 'set_ip_whitelisted';

	protected function execResponse() :bool {
		$con = $this->getCon();
		$modIP = $con->getModule_IPs();

		$ruleStatus = ( new IpRuleStatus( $con->this_req->ip ) )->setMod( $modIP );
		/** @var IpRuleRecord $ipRecord */
		$ipRecord = current( $ruleStatus->getRulesForBypass() );
		if ( !empty( $ipRecord ) ) {
			/** @var Update $updater */
			$updater = $modIP->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $ipRecord );
		}

		$con->this_req->is_ip_whitelisted = true;
		return true;
	}
}