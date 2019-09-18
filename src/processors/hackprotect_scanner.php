<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Scanner extends ShieldProcessor {

	use Shield\Crons\StandardCron;

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
		$this->setupCron();
		$this->handlePostScanCron();
	}

	/**
	 * @param string $sSlug
	 * @return \ICWP_WPSF_Processor_ScanBase|null
	 */
	public function getScannerFromSlug( $sSlug ) {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return in_array( $sSlug, $oOpts->getScanSlugs() ) ? $this->getSubPro( $sSlug ) : null;
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
	 * Responsible for sending out emails and doing any automated repairs.
	 */
	private function handlePostScanCron() {
		add_action( $this->getCon()->prefix( 'post_scan' ), function ( $aScansToNotify ) {
			/** @var HackGuard\Options $oOpts */
			$oOpts = $this->getOptions();
			foreach ( array_intersect( $oOpts->getScanSlugs(), $aScansToNotify ) as $sSlug ) {
				$this->getSubPro( $sSlug )
					 ->cronProcessScanResults();
			}
		} );
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
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$aScans = [];
		foreach ( $oOpts->getScanSlugs() as $sScanSlug ) {
			$oProc = $this->getSubPro( $sScanSlug );
			if ( $oProc->isAvailable() && $oProc->isEnabled() ) {
				$aScans[] = $sScanSlug;
			}
		}

		$oOpts->setIsScanCron( true );
		$oMod->saveModOptions();

		$oMod->getScanController()
			 ->startScans( $aScans );
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
		return $this->getCon()->prefix( $this->getOptions()->getDef( 'cron_all_scans' ) );
	}
}