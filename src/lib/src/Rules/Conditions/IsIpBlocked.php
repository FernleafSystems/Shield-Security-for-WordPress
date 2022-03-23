<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryIpBlock;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;

class IsIpBlocked extends Base {

	use RequestIP;

	const SLUG = 'is_ip_blocked';

	protected function execConditionCheck() :bool {
		return ( new QueryIpBlock() )
			->setMod( $this->getCon()->getModule_IPs() )
			->setIp( $this->getRequestIP() )
			->run();
	}
}