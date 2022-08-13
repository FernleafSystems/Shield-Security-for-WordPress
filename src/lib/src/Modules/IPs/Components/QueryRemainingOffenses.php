<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class QueryRemainingOffenses {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	public function run() :int {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$blackIP = ( new IPs\Lib\Ops\FindIpRuleRecords() )
			->setMod( $mod )
			->setListTypeAutoBlock()
			->setIP( $this->getIP() )
			->firstSingle();

		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->getOffenseLimit() - ( empty( $blackIP ) ? 0 : $blackIP->offenses ) - 1;
	}
}