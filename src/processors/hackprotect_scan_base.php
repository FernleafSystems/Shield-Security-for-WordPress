<?php

if ( class_exists( 'ICWP_WPSF_Processor_ScanBase', false ) ) {
	return;
}

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class ICWP_WPSF_Processor_ScanBase extends ICWP_WPSF_Processor_BaseWpsf {

	use Shield\Crons\StandardCron;
	const SCAN_SLUG = 'base';

	/**
	 * @var ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	protected $oScanner;

	/**
	 */
	public function run() {
		parent::run();
		$this->setupCron();
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	public function doScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oResults = $this->getScannerResults();
		$this->updateScanResultsStore( $oResults );
		$oFO->setLastScanAt( static::SCAN_SLUG );

		return $oResults;
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	protected function getScannerResults() {
		/** @var Shield\Scans\Base\BaseResultsSet $oResults */
		return $this->getScanner()->run();
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	public function doScanAndFullRepair() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		$oResultSet = $this->doScan();
		$this->getRepairer()->repairResultsSet( $oResultSet );
		$oFO->clearLastScanProblemAt( static::SCAN_SLUG );

		return $oResultSet;
	}

	/**
	 * @return mixed
	 */
	abstract protected function getRepairer();

	/**
	 * @return mixed
	 */
	abstract protected function getScanner();

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oNewResults
	 */
	protected function updateScanResultsStore( $oNewResults ) {
		$oNewCopy = clone $oNewResults; // so we don't modify these for later use.
		$oExisting = $this->readScanResultsFromDb();
		$oItemsToDelete = ( new Shield\Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewCopy );
		$this->deleteResultsSet( $oItemsToDelete );
		$this->storeNewScanResults( $oNewCopy );
		$this->updateExistingScanResults( $oExisting );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oToDelete
	 */
	protected function deleteResultsSet( $oToDelete ) {
		( new Shield\Scans\Base\ScanResults\Clean() )
			->setDbHandler( $this->getScannerDb()->getDbHandler() )
			->deleteResults( $oToDelete );
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		/** @var Shield\Databases\Scanner\Select $oSelector */
		$oSelector = $this->getScannerDb()->getDbHandler()->getQuerySelector();
		return $this->convertVosToResults( $oSelector->forScan( static::SCAN_SLUG ) );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oResults
	 */
	protected function storeNewScanResults( $oResults ) {
		$oInsert = $this->getScannerDb()->getDbHandler()->getQueryInserter();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oResults
	 */
	protected function updateExistingScanResults( $oResults ) {
		$oUp = $this->getScannerDb()->getDbHandler()->getQueryUpdater();
		/** @var Shield\Databases\Scanner\EntryVO $oVo */
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oUp->reset()
				->setUpdateData( $oVo->getRawDataAsArray() )
				->setUpdateWheres(
					[
						'scan' => static::SCAN_SLUG,
						'hash' => $oVo->hash,
					]
				)
				->query();
		}
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oResults
	 * @return Shield\Databases\Base\EntryVO[] $aVos
	 */
	abstract protected function convertResultsToVos( $oResults );

	/**
	 * @param Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	abstract protected function convertVosToResults( $aVos );

	/**
	 * @param Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Base\BaseResultItem
	 */
	abstract protected function convertVoToResultItem( $oVo );

	/**
	 * @return $this
	 */
	public function resetIgnoreStatus() {
		/** @var Shield\Databases\Scanner\Handler $oUpd */
		$oDbh = $this->getScannerDb()->getDbHandler();
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
		$oDbh = $this->getScannerDb()->getDbHandler();
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
	 * @throws Exception
	 */
	public function executeItemAction( $sItemId, $sAction ) {
		switch ( $sAction ) {
			case 'delete':
				$bSuccess = $this->deleteItem( $sItemId );
				break;

			case 'ignore':
				$bSuccess = $this->ignoreItem( $sItemId );
				break;

			case 'repair':
				$bSuccess = $this->repairItem( $sItemId );
				break;

			case 'deactivate':
				$bSuccess = $this->deactivateItem( $sItemId );
				break;

			default:
				$bSuccess = false;
				break;
		}

		return $bSuccess;
	}

	/**
	 * @param string $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deleteItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param string $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function ignoreItem( $sItemId ) {
		/** @var Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getScannerDb()
					   ->getDbHandler()
					   ->getQuerySelector()
					   ->byId( $sItemId );
		if ( empty( $oEntry ) ) {
			throw new Exception( 'Item could not be found to ignore.' );
		}

		/** @var Shield\Databases\Scanner\Update $oUp */
		$oUp = $this->getScannerDb()
					->getDbHandler()
					->getQueryUpdater();
		$bSuccess = $oUp->setIgnored( $oEntry );

		if ( !$bSuccess ) {
			throw new Exception( 'Item could not be ignored at this time.' );
		}

		return $bSuccess;
	}

	/**
	 * @param string $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deactivateItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param string $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * Cron callback
	 */
	public function runCron() {
		$this->cronScan();
	}

	private function cronScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}
		$this->doScan();
		$this->cronProcessScanResults();
	}

	/**
	 * Because it's the cron and we'll maybe be notifying user, we look
	 * only for items that have not been notified recently.
	 */
	protected function cronProcessScanResults() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		/** @var Shield\Databases\Scanner\Select $oSel */
		$oSel = $this->getScannerDb()
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
		$oUpd = $this->getScannerDb()->getDbHandler()->getQueryUpdater();
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
			_wpsf__( 'Run Scanner' )
		);
	}

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getScanFrequency();
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	public function getScannerDb() {
		return $this->oScanner;
	}

	/**
	 * @param ICWP_WPSF_Processor_HackProtect_Scanner $oScanner
	 * @return $this
	 */
	public function setScannerDb( $oScanner ) {
		$this->oScanner = $oScanner;
		return $this;
	}
}