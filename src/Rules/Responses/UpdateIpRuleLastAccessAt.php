<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Update;

class UpdateIpRuleLastAccessAt extends Base {

	public const SLUG = 'update_ip_rule_last_access_at';

	public function execResponse() :void {
		foreach ( $this->req->getIpStatus()->getRules() as $rule ) {
			/** @var Update $updater */
			$updater = self::con()->db_con->ip_rules->getQueryUpdater();
			$updater->updateLastAccessAt( $rule );
		}
	}
}
