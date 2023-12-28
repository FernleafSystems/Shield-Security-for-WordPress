<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class UpdateIpRuleLastAccessAt extends Base {

	public const SLUG = 'update_ip_rule_last_access_at';

	public function execResponse() :void {
		$con = self::con();
		foreach ( ( new IpRuleStatus( $this->req->ip ) )->getRules() as $rule ) {
			/** @var Update $updater */
			$updater = $con->getModule_IPs()->getDbH_IPRules()->getQueryUpdater();
			$updater->updateLastAccessAt( $rule );
		}
	}
}