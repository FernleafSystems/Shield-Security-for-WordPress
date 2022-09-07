<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlockedManual extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked_manual';

	/**
	 * @inheritDoc
	 */
	protected function execConditionCheck() :bool {
		$this->getCon()->this_req->is_ip_blocked_shield_manual = ( new IpRuleStatus( $this->getRequestIP() ) )
			->setMod( $this->getCon()->getModule_IPs() )
			->hasManualBlock();
		return $this->getCon()->this_req->is_ip_blocked_shield_manual;
	}
}