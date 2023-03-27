<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class QueryRemainingOffenses {

	use IPs\ModConsumer;
	use IpAddressConsumer;

	public function run() :int {
		return $this->opts()->getOffenseLimit() - ( new IpRuleStatus( $this->getIP() ) )->getOffenses() - 1;
	}
}