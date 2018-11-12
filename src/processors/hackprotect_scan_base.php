<?php

if ( class_exists( 'ICWP_WPSF_Processor_ScanBase', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/cronbase.php' );

use \FernleafSystems\Wordpress\Plugin\Shield\Scans;

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
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		$this->loadAutoload();
	}

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	public function doScan() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		/** @var Scans\Base\BaseResultsSet $oResults */
		$oResults = $this->getScanner()->run();
		$this->updateScanResultsStore( $oResults );

		$oFO->setLastScanAt( static::SCAN_SLUG );
		$oResults->hasItems() ?
			$oFO->setLastScanProblemAt( static::SCAN_SLUG )
			: $oFO->clearLastScanProblemAt( static::SCAN_SLUG );

		return $oResults;
	}

	/**
	 * @return Scans\Base\BaseResultsSet
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
	 * @param Scans\Base\BaseResultsSet $oNewResults
	 */
	protected function updateScanResultsStore( $oNewResults ) {
		$oExisting = $this->readScanResultsFromDb();
		$oItemsToDelete = ( new Scans\Base\DiffResultForStorage() )->diff( $oExisting, $oNewResults );
		$this->deleteResultsSet( $oItemsToDelete );
		$this->storeNewScanResults( $oNewResults );
		$this->updateExistingScanResults( $oExisting );
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oToDelete
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
	 * @return Scans\Base\BaseResultsSet
	 */
	protected function readScanResultsFromDb() {
		$oSelector = $this->getScannerDb()->getQuerySelector();
		return $this->convertVosToResults( $oSelector->forScan( static::SCAN_SLUG ) );
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oResults
	 */
	protected function storeNewScanResults( $oResults ) {
		$oInsert = $this->getScannerDb()->getQueryInserter();
		foreach ( $this->convertResultsToVos( $oResults ) as $oVo ) {
			$oInsert->insert( $oVo );
		}
	}

	/**
	 * @param Scans\Base\BaseResultsSet $oResults
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
	 * @param Scans\Base\BaseResultsSet $oResults
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseEntryVO[] $aVos
	 */
	abstract protected function convertResultsToVos( $oResults );

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseEntryVO[] $aVos
	 * @return Scans\Base\BaseResultsSet
	 */
	abstract protected function convertVosToResults( $aVos );

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