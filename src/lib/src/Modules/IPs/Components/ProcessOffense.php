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

	public function run() {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		try {
			$oIP = ( new IPs\Lib\Ops\AddIp() )
				->setMod( $mod )
				->setIP( $this->getIP() )
				->toAutoBlacklist();
		}
		catch ( \Exception $e ) {
			$oIP = null;
		}

		if ( $oIP instanceof Databases\IPs\EntryVO ) {
			$nCurrent = $oIP->transgressions;

			$oTracker = $mod->loadOffenseTracker();
			$nNewTotal = $oIP->transgressions + $oTracker->getOffenseCount();
			$bToBlock = $oTracker->isBlocked() ||
						( $oIP->blocked_at == 0 && ( $nNewTotal >= $opts->getOffenseLimit() ) );

			/** @var Databases\IPs\Update $oUp */
			$oUp = $mod->getDbHandler_IPs()->getQueryUpdater();
			$oUp->updateTransgressions( $oIP, $nNewTotal );

			$con->fireEvent( $bToBlock ? 'ip_blocked' : 'ip_offense',
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
				$oUp = $mod->getDbHandler_IPs()->getQueryUpdater();
				$oUp->setBlocked( $oIP );
				$con->fireEvent( 'ip_offense', [ 'suppress_audit' => true ] );
			}
		}
	}
}