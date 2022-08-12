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
		$blackIp = ( new IPs\Lib\Ops\LookupIP() )
			->setMod( $mod )
			->setListTypeBlock()
			->setIP( $this->getIP() )
			->lookup( false );

		$offenses = empty( $blackIp ) ? 0 : $blackIp->offenses;

		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->getOffenseLimit() - $offenses - 1;
	}
}