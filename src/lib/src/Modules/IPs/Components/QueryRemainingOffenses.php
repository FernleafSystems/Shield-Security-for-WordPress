<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

/**
 * Class QueryRemainingOffenses
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class QueryRemainingOffenses {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	/**
	 * @return int
	 */
	public function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$blackIp = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBlock()
			->setIP( $this->getIP() )
			->lookup( false );

		$offenses = 0;
		if ( $blackIp instanceof Databases\IPs\EntryVO ) {
			$offenses = (int)$blackIp->transgressions;
		}

		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->getOffenseLimit() - $offenses - 1;
	}
}