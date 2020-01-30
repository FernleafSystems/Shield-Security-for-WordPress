<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

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
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oBlackIp = ( new Ops\LookupIpOnList() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setListTypeBlack()
			->setIP( $this->getIP() )
			->lookup( false );

		$nOffenses = 0;
		if ( $oBlackIp instanceof IPs\EntryVO ) {
			$nOffenses = (int)$oBlackIp->transgressions;
		}

		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getOffenseLimit() - $nOffenses - 1;
	}
}