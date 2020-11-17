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
		$oBlackIp = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBlack()
			->setIP( $this->getIP() )
			->lookup( false );

		$nOffenses = 0;
		if ( $oBlackIp instanceof Databases\IPs\EntryVO ) {
			$nOffenses = (int)$oBlackIp->transgressions;
		}

		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getOffenseLimit() - $nOffenses - 1;
	}
}