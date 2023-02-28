<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class QueryRemainingOffenses {

	use ModConsumer;
	use IpAddressConsumer;

	public const MOD = IPs\ModCon::SLUG;

	public function run() :int {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->getOffenseLimit() - ( new IpRuleStatus( $this->getIP() ) )->getOffenses() - 1;
	}
}