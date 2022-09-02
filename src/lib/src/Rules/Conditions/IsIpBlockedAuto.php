<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlockedAuto extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked_auto';

	/**
	 * @inheritDoc
	 */
	protected function execConditionCheck() :bool {
		return ( new IpRuleStatus( $this->getRequestIP() ) )
			->setMod( $this->getCon()->getModule_IPs() )
			->hasAutoBlock();
	}
}