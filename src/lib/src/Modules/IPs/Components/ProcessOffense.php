<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

/**
 * NOT IMPLEMENTED
 * Class ProcessOffense
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class ProcessOffense {

	use Shield\Modules\ModConsumer;
	use IpAddressConsumer;

	/**
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$oIP = ( new IPs\Lib\Ops\AddIp() )
			->setMod( $oMod )
			->setIP( $this->getIP() )
			->toAutoBlacklist();

		if ( $oIP instanceof Databases\IPs\EntryVO ) {
			$nCurrent = $oIP->transgressions;

			$oTracker = $oMod->loadOffenseTracker();
			$bToBlock = $oTracker->isBlocked() ||
						( $oIP->blocked_at == 0 && ( $oOpts->getOffenseLimit() - $nCurrent == 1 ) );
			$nNewTotal = $oIP->transgressions + $oTracker->getOffenseCount();

			/** @var Databases\IPs\Update $oUp */
			$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
			$oUp->updateTransgressions( $oIP, $nNewTotal );

			$oCon->fireEvent( $bToBlock ? 'ip_blocked' : 'ip_offense',
				[
					'audit' => [
						'from' => $nCurrent,
						'to'   => $nNewTotal,
					]
				]
			);

			/**
			 * When we block, we also want to increment offense stat, but we don't
			 * want to also audit the offense (only audit the block),
			 * so we fire ip_offense but suppress the audit
			 */
			if ( $bToBlock ) {
				$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
				$oUp->setBlocked( $oIP );
				$oCon->fireEvent( 'ip_offense', [ 'suppress_audit' => true ] );
			}
		}
	}
}