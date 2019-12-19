<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class QueryIpBlock
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class QueryIpBlock {

	use Shield\Modules\ModConsumer;

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
		if ( $oIP instanceof IPs\EntryVO ) {

			$bIpBlocked = true;

			/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
			$oMod = $this->getMod();
			/** @var IPs\Update $oUp */
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
	 * @return IPs\EntryVO|null
	 */
	private function getBlockedIpRecord() {
		$oBlockIP = null;

		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oIP = ( new Ops\LookupIpOnList() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIp( $this->getIP() )
			->setListTypeBlack()
//			->setIsIpBlocked( true ) TODO: 8.6
			->lookup();

		if ( $oIP instanceof IPs\EntryVO ) {
			/** @var Options $oOpts */
			$oOpts = $this->getOptions();

			// Clean out old IPs as we go so they don't show up in future queries.
			if ( $oIP->list == $oMod::LIST_AUTO_BLACK
				 && $oIP->last_access_at < Services::Request()->ts() - $oOpts->getAutoExpireTime() ) {

				( new Ops\DeleteIpFromBlackList() )
					->setDbHandler( $oMod->getDbHandler_IPs() )
					->run( Services::IP()->getRequestIp() );
			}
			elseif ( $oIP->blocked_at > 0 || (int)$oIP->transgressions >= $oOpts->getOffenseLimit() ) {
				$oBlockIP = $oIP;
			}
		}

		return $oBlockIP;
	}

	/**
	 * @return string
	 */
	public function getIP() {
		return $this->sIP;
	}

	/**
	 * @param string $sIP
	 * @return $this
	 */
	public function setIp( $sIP ) {
		$this->sIP = $sIP;
		return $this;
	}
}