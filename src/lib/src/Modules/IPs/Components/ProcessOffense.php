<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Services\Services;

/**
 * NOT IMPLEMENTED
 * Class ProcessOffense
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components
 */
class ProcessOffense {

	use Shield\Modules\ModConsumer;

	/**
	 * @var string
	 */
	private $sIP;

	/**
	 * @return bool - true if IP is blocked, false otherwise
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$oCon = $this->getCon();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		$oBlackIp = ( new IPs\Lib\Ops\LookupIpOnList() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIp( Services::IP()->getRequestIp() )
			->setListTypeBlack()
			->lookup();

		if ( !$oBlackIp instanceof Databases\IPs\EntryVO ) {
			$oBlackIp = $this->addIpToList(
				Services::IP()->getRequestIp(),
				$oMod::LIST_AUTO_BLACK,
				'auto'
			);
		}

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