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
	 * TODO: Generic so lift out of base
	 * @param Shield\Scans\Base\BaseScanActionVO $oAction
	 */
	public function postScanActionProcess( $oAction ) {

		if ( $oAction->is_cron ) {
			$this->cronProcessScanResults();
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
		( new Scan\Results\Clean() )
			->setDbHandler( $this->getMod()->getDbHandler() )
			->deleteResults( $oToDelete );
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		/** @var Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $this->getMod()->getDbHandler()->getQuerySelector();
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
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getMod()
					 ->getDbHandler()
					 ->getQuerySelector();
		/** @var Shield\Databases\Scanner\EntryVO $oVo */
		$oVo = $oSel->filterByHash( $oItem->hash )
					->filterByScan( $this->getScanActionVO()->scan )
					->first();
		return $oVo;
	}

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var Shield\Databases\Scanner\Handler $oUpd */
		$oDbh = $this->getMod()->getDbHandler();
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
		/** @var Shield\Databases\Scanner\Handler $oUpd */
		$oDbh = $this->getMod()->getDbHandler();
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
		$bSuccess = false;
		if ( is_numeric( $sItemId ) ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			$oEntry = $this->getMod()
						   ->getDbHandler()
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
					$bSuccess = false;
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

		/** @var Shield\Databases\Scanner\Update $oUp */
		$oUp = $this->getMod()
					->getDbHandler()
					->getQueryUpdater();

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
	protected function cronProcessScanResults() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getMod()
					 ->getDbHandler()
					 ->getQuerySelector();
		/** @var Shield\Databases\Scanner\EntryVO[] $aRes */
		$aRes = $oSel->filterByScan( static::SCAN_SLUG )
					 ->filterForCron( $oFO->getScanNotificationInterval() )
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
		/** @var Shield\Databases\Scanner\Update $oUpd */
		$oUpd = $this->getMod()->getDbHandler()->getQueryUpdater();
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
		( new Scan\Results\Clean() )
			->setDbHandler( $this->getMod()->getDbHandler() )
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