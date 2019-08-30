<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Scanner extends ShieldProcessor {

	use Shield\Crons\StandardCron;

	/**
	 * @var Shield\Scans\Common\AsyncScansController
	 */
	private $oAsyncScanController;

	/**
	 * @var Shield\Modules\HackGuard\ScanQueue\Controller
	 */
	private $oScanQueueController;

	/**
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$this->getSubProcessorApc()->execute();
		$this->getSubProcessorUfc()->execute();
		$this->getSubProcessorWcf()->execute();
		if ( $oMod->isPremium() ) {
			$this->getSubProcessorMal()->execute();
			$this->getSubProcessorWpv()->execute();
			if ( $oMod->isPtgEnabled() ) {
				$this->getSubProcessorPtg()->execute();
			}
		}
		try {
			$this->setupScanQueue();
		}
		catch ( Exception $e ) {
			var_dump($e);
			die();
		}
		$this->handleAsyncScanRequest();
		$this->setupCron();
	}

	/**
	 * @param string[] $aScans
	 */
	public function launchScans( $aScans ) {
		$this->getAsyncScanController()
			 ->abortAllScans() // TODO: not abort all, but append?
			 ->setupNewScanJob( $aScans );
		$this->processAsyncScans();
	}

	public function getScanQueue() {
		return $this->setupScanQueue();
	}

	/**
	 * @return Shield\Modules\HackGuard\ScanQueue\Controller
	 * @throws \Exception
	 */
	private function setupScanQueue() {
		if ( !isset( $this->oScanQueueController ) ) {
			$this->oScanQueueController = ( new Shield\Modules\HackGuard\ScanQueue\Controller() )
				->setMod( $this->getMod() );
		}
		$this->oScanQueueController->getQueueProcessor();
		return $this->oScanQueueController;
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
			if ( $oAction->finished_at > 0 ) {
				$this->getSubPro( $oAction->scan )
					 ->postScanActionProcess( $oAction );
			}
		}
		catch ( \Exception $oE ) {
//			error_log( $oE->getMessage() );
		}
	}

	/**
	 * @return Shield\Scans\Common\AsyncScansController
	 */
	public function getAsyncScanController() {
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
		$oJob = $oS->loadScansJob();
		foreach ( $oMod->getAllScanSlugs() as $sSlug ) {
			$aRunning[ $sSlug ] = $oJob->isScanInited( $sSlug );
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
	 * Cron callback
	 */
	public function runCron() {
		Services::WpGeneral()->getIfAutoUpdatesInstalled() ? $this->resetCron() : $this->cronScan();
	}

	private function cronScan() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$aScansToRun = array_filter(
			$oMod->getAllScanSlugs(),
			function ( $sScanSlug ) {
				$oProc = $this->getSubPro( $sScanSlug );
				return $oProc->isAvailable() && $oProc->isEnabled();
			}
		);

		if ( !empty( $aScansToRun ) ) {
			$this->launchScans( $aScansToRun );
		}
	}

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		return $oOpts->getScanFrequency();
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		return $oOpts->getDef( 'cron_all_scans' );
	}
}