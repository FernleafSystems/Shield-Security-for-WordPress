<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class QueryRemainingOffenses {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() :int {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		$offenses = ( new IpRuleStatus( $this->getIP() ) )
			->setMod( $mod )
			->getOffenses();

		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->getOffenseLimit() - $offenses - 1;
	}
}