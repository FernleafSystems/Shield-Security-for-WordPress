<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class QueryIpBlock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class QueryIpBlock {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	/**
	 * @var string
	 */
	private $sIP;

	/**
	 * @return bool - true if IP is blocked, false otherwise
	 */
	public function run() {
		$bIpBlocked = false;

		$oIP = $this->getBlockedIpRecord();
		if ( $oIP instanceof Databases\IPs\EntryVO ) {

			$bIpBlocked = true;

			/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
			$oMod = $this->getMod();
			/** @var Databases\IPs\Update $oUp */
			$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
			$oUp->updateLastAccessAt( $oIP );

			// TODO: 8.6: remove eventually and lose transgressions comparison and query for "blocked" only (see below)
			if ( $oIP->blocked_at == 0 ) {
				$oUp->reset()->setBlocked( $oIP );
			}
		}
		return $bIpBlocked;
	}

	/**
	 * @return Databases\IPs\EntryVO|null
	 */
	private function getBlockedIpRecord() {
		$oBlockIP = null;

		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oIP = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIP( $this->getIP() )
			->setListTypeBlack()
//			->setIsIpBlocked( true ) TODO: 8.6
			->lookup();

		if ( $oIP instanceof Databases\IPs\EntryVO ) {
			/** @var IPs\Options $oOpts */
			$oOpts = $this->getOptions();

			// Clean out old IPs as we go so they don't show up in future queries.
			if ( $oIP->list == $oMod::LIST_AUTO_BLACK
				 && $oIP->last_access_at < Services::Request()->ts() - $oOpts->getAutoExpireTime() ) {

				( new IPs\Lib\Ops\DeleteIp() )
					->setDbHandler( $oMod->getDbHandler_IPs() )
					->setIP( Services::IP()->getRequestIp() )
					->fromBlacklist();
			}
			elseif ( $oIP->blocked_at > 0 || (int)$oIP->transgressions >= $oOpts->getOffenseLimit() ) {
				$oBlockIP = $oIP;
			}
		}

		return $oBlockIP;
	}
}