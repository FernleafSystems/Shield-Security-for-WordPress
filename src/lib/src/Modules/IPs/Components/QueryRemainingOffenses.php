<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class QueryRemainingOffenses {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	public function run() :int {
		return self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit()
			   - ( new IpRuleStatus( $this->getIP() ) )->getOffenses()
			   - 1;
	}
}