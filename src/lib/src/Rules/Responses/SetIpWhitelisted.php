<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\FindIpRuleRecords;

class SetIpWhitelisted extends Base {

	const SLUG = 'set_ip_whitelisted';

	protected function execResponse() :bool {
		$con = $this->getCon();
		$modIP = $con->getModule_IPs();

		$ipRecord = ( new FindIpRuleRecords() )
			->setMod( $modIP )
			->setIP( $con->this_req->ip )
			->setListTypeBypass()
			->firstSingle();
		if ( !empty( $ipRecord ) ) {
			/** @var Update $updater */
			$updater = $modIP->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $ipRecord );
		}

		$con->this_req->is_ip_whitelisted = true;
		return true;
	}
}