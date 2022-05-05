<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpHighReputation extends Base {

	use RequestIP;

	const SLUG = 'is_ip_high_reputation';

	protected function execConditionCheck() :bool {
		return ( new IsHighReputationIP() )
			->setMod( $this->getCon()->getModule_IPs() )
			->setIP( $this->getRequestIP() )
			->query();
	}
}