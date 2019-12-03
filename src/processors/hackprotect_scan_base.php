<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;
use FernleafSystems\Wordpress\Services\Services;

abstract class ICWP_WPSF_Processor_ScanBase extends Shield\Modules\BaseShield\ShieldProcessor {

	use Shield\Scans\Common\ScanActionConsumer;
	const SCAN_SLUG = 'base';

	/**
	 * @var ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	protected $oScanner;

	public function run() {
		add_action( $this->getCon()->prefix( 'ondemand_scan_'.static::SCAN_SLUG ), function () {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			$oMod->getScanController()
				 ->startScans( [ static::SCAN_SLUG ] );
		} );
	}

	/**
	 * @return bool
	 */
	public function isAvailable() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function isRestricted() {
		return true;
	}

	/**
	 * @return bool
	 */
	abstract public function isEnabled();

	/**
	 */
	public function hookOnDemandScan() {
		$this->scheduleOnDemandScan();
	}

	/**
	 * @param int $nDelay
	 */
	public function scheduleOnDemandScan( $nDelay = 3 ) {
		$sHook = $this->getCon()->prefix( 'ondemand_scan_'.static::SCAN_SLUG );
		if ( !wp_next_scheduled( $sHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + $nDelay, $sHook );
		}
	}

	/**
	 * @return Shield\Scans\Base\BaseRepair|mixed|null
	 */
	abstract protected function getRepairer();

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	public function getScanActionVO() {
		if ( !$this->oScanActionVO instanceof Shield\Scans\Base\BaseScanActionVO ) {
			$oAct = $this->getNewActionVO();
			$oAct->scan = static::SCAN_SLUG;
			$this->oScanActionVO = $oAct;
		}

		return $this->oScanActionVO;
	}

	/**
	 * Override this to provide the correct VO
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	protected function getNewActionVO() {
		return ( new Scan\ScanActionFromSlug() )->getAction( static::SCAN_SLUG );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oToDelete
	 */
	protected function deleteResultsSet( $oToDelete ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		( new Scan\Results\Clean() )
			->setDbHandler( $oMod->getDbHandler_ScanResults() )
			->deleteResults( $oToDelete );
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		return $this->convertVosToResults( $oSelector->forScan( static::SCAN_SLUG ) );
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Base\BaseResultsSet|mixed
	 */
	protected function convertVosToResults( $aVos ) {
		return ( new Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() )
			->fromVOsToResultsSet( $aVos );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return Shield\Databases\Scanner\EntryVO|null
	 */
	protected function getVoFromResultItem( $oItem ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		return $oSel->filterByHash( $oItem->hash )
					->filterByScan( $this->getScanActionVO()->scan )
					->first();
	}

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbh = $oMod->getDbHandler_ScanResults();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $oDbh->getQuerySelector();

		/** @var Shield\Databases\Scanner\Update $oUpd */
		$oUpd = $oDbh->getQueryUpdater();
		foreach ( $oSel->forScan( static::SCAN_SLUG ) as $oEntry ) {
			$oUpd->reset()->setNotIgnored( $oEntry );
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function resetNotifiedStatus() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbh = $oMod->getDbHandler_ScanResults();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $oDbh->getQuerySelector();

		/** @var Shield\Databases\Scanner\Update $oUpd */
		$oUpd = $oDbh->getQueryUpdater();
		foreach ( $oSel->forScan( static::SCAN_SLUG ) as $oEntry ) {
			$oUpd->reset()->setNotNotified( $oEntry );
		}
		return $this;
	}

	/**
	 * @param int|string $sItemId
	 * @param string     $sAction
	 * @return bool
	 * @throws \Exception
	 */
	public function executeItemAction( $sItemId, $sAction ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$bSuccess = false;
		if ( is_numeric( $sItemId ) ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oEntry = $oMod->getDbHandler_ScanResults()
						   ->getQuerySelector()
						   ->byId( $sItemId );
			if ( empty( $oEntry ) ) {
				throw new \Exception( 'Item could not be found.' );
			}

			$oItem = ( new Scan\Results\ConvertBetweenTypes() )
				->setScanActionVO( $this->getScanActionVO() )
				->convertVoToResultItem( $oEntry );

			switch ( $sAction ) {
				case 'delete':
					$bSuccess = $this->itemDelete( $oItem );
					break;

				case 'ignore':
					$bSuccess = $this->itemIgnore( $oItem );
					error_log( var_export( $bSuccess, true ) );
					break;

				case 'repair':
					$bSuccess = $this->itemRepair( $oItem );
					break;

				case 'accept':
					$bSuccess = $this->itemAccept( $oItem );
					break;

				case 'asset_accept':
					$bSuccess = $this->assetAccept( $oItem );
					break;

				case 'asset_deactivate':
					$bSuccess = $this->assetDeactivate( $oItem );
					break;

				case 'asset_reinstall':
					$bSuccess = $this->assetReinstall( $oItem );
					break;

				default:
					throw new \Exception( 'Unsupported Scan Item Action' );
					break;
			}
		}

		return $bSuccess;
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetAccept( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * Only plugins may be deactivated, of course.
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetDeactivate( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function assetReinstall( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemAccept( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemDelete( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemIgnore( $oItem ) {

		/** @var Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getVoFromResultItem( $oItem );
		if ( empty( $oEntry ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Databases\Scanner\Update $oUp */
		$oUp = $oMod->getDbHandler_ScanResults()->getQueryUpdater();

		if ( !$oUp->setIgnored( $oEntry ) ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}

	/**
	 * @param Shield\Scans\Base\BaseResultItem $oItem
	 * @return bool
	 * @throws \Exception
	 */
	protected function itemRepair( $oItem ) {
		throw new \Exception( 'Unsupported Action' );
	}

	/**
	 * Because it's the cron and we'll maybe be notifying user, we look
	 * only for items that have not been notified recently.
	 */
	public function cronProcessScanResults() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		/** @var Shield\Databases\Scanner\EntryVO[] $aRes */
		$aRes = $oSel->filterByScan( static::SCAN_SLUG )
					 ->filterForCron( $oMod->getScanNotificationInterval() )
					 ->query();

		if ( !empty( $aRes ) ) {
			$oRes = $this->convertVosToResults( $aRes );

			$this->runCronAutoRepair( $oRes );

			if ( $this->runCronUserNotify( $oRes ) ) {
				$this->updateLastNotifiedAt( $aRes );
			}
		}
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRes
	 */
	protected function runCronAutoRepair( $oRes ) {
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oRes
	 * @return bool - true if user notified
	 */
	protected function runCronUserNotify( $oRes ) {
		return false;
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aRes
	 */
	private function updateLastNotifiedAt( $aRes ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Databases\Scanner\Update $oUpd */
		$oUpd = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		foreach ( $aRes as $oVo ) {
			$oUpd->reset()
				 ->setNotified( $oVo );
		}
	}

	/**
	 * @return string
	 */
	protected function getScannerButtonForEmail() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return sprintf( '<a href="%s" target="_blank" style="%s">%s →</a>',
			$oFO->getUrlManualScan(),
			'border:2px solid #e66900;padding:20px;line-height:19px;margin:15px 20px 10px;display:inline-block;text-align:center;width:200px;font-size:18px;color: #e66900;border-radius:3px;',
			__( 'Run Scanner', 'wp-simple-firewall' )
		);
	}

	/**
	 */
	public function deactivatePlugin() {
		$this->resetScan();
	}

	/**
	 * @return $this
	 */
	public function resetScan() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		( new Scan\Results\Clean() )
			->setDbHandler( $oMod->getDbHandler_ScanResults() )
			->setScanActionVO( $this->getScanActionVO() )
			->deleteAllForScan();
		return $this;
	}

	/**
	 * @return \ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	public function getScannerDb() {
		return $this->oScanner;
	}

	/**
	 * @param \ICWP_WPSF_Processor_HackProtect_Scanner $oScanner
	 * @return $this
	 */
	public function setScannerDb( $oScanner ) {
		$this->oScanner = $oScanner;
		return $this;
	}
}