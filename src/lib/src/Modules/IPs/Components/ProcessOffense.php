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

		$oBlackIp = ( new IPs\Lib\Ops\AddIp() )
			->setMod( $oMod )
			->setIP( $this->getIP() )
			->toAutoBlacklist();

		if ( $oBlackIp instanceof Databases\IPs\EntryVO ) {
			$nLimit = $oOpts->getOffenseLimit();
			$nCurrentOffenses = $oBlackIp->transgressions;

			$mAction = $oMod->getIpOffenceCount();
			$bToBlock = ( $oBlackIp->blocked_at == 0 ) && ( $nCurrentOffenses < $nLimit )
						&& ( $mAction == PHP_INT_MAX ) || ( $nLimit - $nCurrentOffenses == 1 );

			$nNewOffensesTotal = $oBlackIp->transgressions +
								 min( 1, $bToBlock ? $nLimit - $nCurrentOffenses : $mAction );

			/** @var Databases\IPs\Update $oUp */
			$oUp = $oMod->getDbHandler_IPs()->getQueryUpdater();
			$oUp->updateTransgressions( $oBlackIp, $nNewOffensesTotal );

			$oCon->fireEvent( $bToBlock ? 'ip_blocked' : 'ip_offense',
				[
					'audit' => [
						'from' => $nCurrentOffenses,
						'to'   => $nNewOffensesTotal,
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
				$oUp->setBlocked( $oBlackIp );
				$oCon->fireEvent( 'ip_offense', [ 'suppress_audit' => true ] );
			}
		}
	}
}