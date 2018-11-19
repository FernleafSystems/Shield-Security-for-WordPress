<?php

if ( class_exists( 'ICWP_WPSF_Processor_ScanBase', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class ICWP_WPSF_Processor_ScanBase extends ICWP_WPSF_Processor_CronBase {

	const SCAN_SLUG = 'base';

	/**
	 * @var ICWP_WPSF_Processor_HackProtect_Scanner
	 */
	protected $oScanner;

	/**
	 */
	public function run() {
		parent::run();
		$this->loadAutoload();
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
		$oResults->hasItems() ?
			$oFO->setLastScanProblemAt( static::SCAN_SLUG )
			: $oFO->clearLastScanProblemAt( static::SCAN_SLUG );

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
		$oDeleter = $this->getScannerDb()->getQueryDeleter();
		foreach ( $oToDelete->getAllItems() as $oItem ) {
			$oDeleter->reset()
					 ->filterByScan( static::SCAN_SLUG )
					 ->filterByHash( $oItem->hash )
					 ->query();
		}
	}

	/**
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		$oSelector = $this->getScannerDb()->getQuerySelector();
		return $this->convertVosToResults( $oSelector->forScan( static::SCAN_SLUG ) );
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oResults
	 */
	protected function storeNewScanResults( $oResults ) {
		$oInsert = $this->getScannerDb()->getQueryInserter();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}

	/**
	 * @param Shield\Scans\Base\BaseResultsSet $oResults
	 */
	protected function updateExistingScanResults( $oResults ) {
		$oUp = $this->getScannerDb()->getQueryUpdater();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oUp->reset()
				->setUpdateData( $oVo->getRawData() )
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
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseEntryVO[] $aVos
	 */
	abstract protected function convertResultsToVos( $oResults );

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO[] $aVos
	 * @return Shield\Scans\Base\BaseResultsSet
	 */
	abstract protected function convertVosToResults( $aVos );

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oVo
	 * @return Shield\Scans\Base\BaseResultItem
	 */
	abstract protected function convertVoToResultItem( $oVo );

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

			default:
				$bSuccess = false;
				break;
		}

		return $bSuccess;
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function deleteItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function ignoreItem( $sItemId ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry */
		$oEntry = $this->getScannerDb()
					   ->getQuerySelector()
					   ->byId( $sItemId );
		if ( empty( $oEntry ) ) {
			throw new Exception( 'Item could not be found to ignore.' );
		}

		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO $oEntry */
		$bSuccess = $this->getScannerDb()
						 ->getQueryUpdater()
						 ->setIgnored( $oEntry );
		if ( !$bSuccess ) {
			throw new Exception( 'Item could not be ignored at this time.' );
		}

		return $bSuccess;
	}

	/**
	 * @param $sItemId
	 * @return bool
	 * @throws Exception
	 */
	protected function repairItem( $sItemId ) {
		throw new Exception( 'Unsupported Action' );
	}

	/**
	 * @return callable
	 */
	protected function getCronCallback() {
		return array( $this, 'cronScan' );
	}

	public function cronScan() {
		if ( doing_action( 'wp_maybe_auto_update' ) || did_action( 'wp_maybe_auto_update' ) ) {
			return;
		}

		$this->doScan();

		$aRes = $this->getScannerDb()
					 ->getQuerySelector()
					 ->filterByNotIgnored()
					 ->filterByScan( static::SCAN_SLUG )
					 ->query();
		if ( !empty( $aRes ) ) {
			$this->handleScanResults( $aRes );
		}
	}

	/**
	 * @param Shield\Databases\Scanner\EntryVO[]
	 */
	protected function handleScanResults( $aRes ) {
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