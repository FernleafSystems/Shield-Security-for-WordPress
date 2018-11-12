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