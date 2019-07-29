<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Scanner extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var Shield\Scans\Common\AsyncScansController
	 */
	private $oAsyncScanController;

	/**
	 * ICWP_WPSF_Processor_HackProtect_Scanner constructor.
	 * @param ICWP_WPSF_FeatureHandler_HackProtect $oModCon
	 */
	public function __construct( $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getDef( 'table_name_scanner' ) );
	}

	/**
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$this->getSubProcessorApc()->run();
		$this->getSubProcessorUfc()->run();
		$this->getSubProcessorWcf()->run();
		if ( $oMod->isPremium() ) {
			$this->getSubProcessorMal()->run();
			$this->getSubProcessorWpv()->run();
			if ( $oMod->isPtgEnabled() ) {
				$this->getSubProcessorPtg()->run();
			}
		}

		$this->handleAsyncScanRequest();
	}

	/**
	 * @param string[] $aScans
	 */
	public function launchScans( $aScans ) {
		$this->getAsyncScanController()
			 ->setScansInitiated( $aScans );
		$this->processAsyncScans();
	}

	/**
	 *
	 */
	private function handleAsyncScanRequest() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$bIsScanRequest = ( !Services::WpGeneral()->isAjax() &&
							$this->getCon()->getShieldAction() == 'scan_async_process'
							&& Services::Request()->query( 'scan_key' ) == $oOpts->getScanKey() );
		if ( $bIsScanRequest ) {
			$this->processAsyncScans();
			die();
		}
	}

	/**
	 */
	private function processAsyncScans() {
		try {
			$oAction = $this->getAsyncScanController()->runScans();
			if ( $oAction->ts_finish > 0 ) {
				$this->getSubPro( $oAction->id )
					 ->postScanActionProcess( $oAction );
			}
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @return Shield\Scans\Common\AsyncScansController
	 */
	private function getAsyncScanController() {
		if ( empty( $this->oAsyncScanController ) ) {
			$this->oAsyncScanController = ( new Shield\Scans\Common\AsyncScansController() )
				->setMod( $this->getMod() );
		}
		return $this->oAsyncScanController;
	}

	/**
	 * @param string $sSlug
	 * @return ICWP_WPSF_Processor_ScanBase|null
	 */
	public function getScannerFromSlug( $sSlug ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return in_array( $sSlug, $oMod->getAllScanSlugs() ) ? $this->getSubPro( $sSlug ) : null;
	}

	/**
	 * @return bool[]
	 */
	public function getScansRunningStates() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$aRunning = [];

		$oS = $this->getAsyncScanController();
		$oS->cleanStaleScans();
		foreach ( $oMod->getAllScanSlugs() as $sSlug ) {
			$aRunning[ $sSlug ] = $oS->isScanInited( $sSlug );
		}
		return $aRunning;
	}

	/**
	 * @return string[]
	 */
	public function getRunningScans() {
		return array_keys( array_filter( $this->getScansRunningStates() ) );
	}

	/**
	 * @return bool
	 */
	public function hasRunningScans() {
		return count( $this->getRunningScans() ) > 0;
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Apc
	 */
	public function getSubProcessorApc() {
		return $this->getSubPro( 'apc' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc
	 */
	protected function getSubProcessorIntegrity() {
		return $this->getSubPro( 'int' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ptg
	 */
	public function getSubProcessorPtg() {
		return $this->getSubPro( 'ptg' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ufc
	 */
	public function getSubProcessorUfc() {
		return $this->getSubPro( 'ufc' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Mal
	 */
	public function getSubProcessorMal() {
		return $this->getSubPro( 'mal' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wcf
	 */
	public function getSubProcessorWcf() {
		return $this->getSubPro( 'wcf' );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Wpv
	 */
	public function getSubProcessorWpv() {
		return $this->getSubPro( 'wpv' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'apc' => 'ICWP_WPSF_Processor_HackProtect_Apc',
			'int' => 'ICWP_WPSF_Processor_HackProtect_Integrity',
			'mal' => 'ICWP_WPSF_Processor_HackProtect_Mal',
			'ptg' => 'ICWP_WPSF_Processor_HackProtect_Ptg',
			'ufc' => 'ICWP_WPSF_Processor_HackProtect_Ufc',
			'wcf' => 'ICWP_WPSF_Processor_HackProtect_Wcf',
			'wpv' => 'ICWP_WPSF_Processor_HackProtect_Wpv',
		];
	}

	/**
	 * @param string $sKey
	 * @return ICWP_WPSF_Processor_ScanBase|mixed|null
	 */
	protected function getSubPro( $sKey ) {
		/** @var ICWP_WPSF_Processor_ScanBase $oPro */
		$oPro = parent::getSubPro( $sKey );
		return $oPro->setScannerDb( $this );
	}

	/**
	 * @return Scanner\Handler
	 */
	protected function createDbHandler() {
		return new Scanner\Handler();
	}

	/**
	 * Based on the Ajax Download File pathway (hence the cookie)
	 * @param string $sItemId
	 */
	public function downloadItemFile( $sItemId ) {
		/** @var Scanner\EntryVO $oEntry */
		$oEntry = $this->getMod()
					   ->getDbHandler()
					   ->getQuerySelector()
					   ->byId( (int)$sItemId );
		if ( !empty( $oEntry ) ) {
			$sPath = $oEntry->meta[ 'path_full' ];
			$oFs = Services::WpFs();
			if ( $oFs->isFile( $sPath ) ) {
				header( 'Set-Cookie: fileDownload=true; path=/' );
				Services::Response()->downloadStringAsFile( $oFs->getFileContent( $sPath ), basename( $sPath ) );
			}
		}

		wp_die( "Something about this request wasn't right" );
	}

	/**
	 * @return string
	 */
	protected function getCreateTableSql() {
		return "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			hash varchar(32) NOT NULL DEFAULT '' COMMENT 'Unique Item Hash',
			meta text COMMENT 'Relevant Item Data',
			scan varchar(10) NOT NULL DEFAULT 0 COMMENT 'Scan Type',
			severity int(3) NOT NULL DEFAULT 1 COMMENT 'Severity',
			ignored_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Ignored',
			notified_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Last Notified',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Discovered',
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'TS Soft Deleted',
			PRIMARY KEY  (id)
		) %s;";
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'table_columns_scanner' );
		return ( is_array( $aDef ) ? $aDef : [] );
	}
}