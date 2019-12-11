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

		$this->getSubPro( 'apc' )->execute();
		$this->getSubPro( 'ufc' )->execute();
		$this->getSubPro( 'wcf' )->execute();
		$this->getSubPro( 'ptg' )->execute();
		if ( $oMod->isPremium() ) {
			$this->getSubPro( 'mal' )->execute();
			$this->getSubPro( 'wpv' )->execute();
		}
		$this->setupCron();
		$this->handlePostScanCron();
	}

	/**
	 * @param string $sSlug
	 * @return \ICWP_WPSF_Processor_ScanBase|null
	 */
	public function getScannerFromSlug( $sSlug ) {
		return $this->getSubPro( $sSlug );
	}

	/**
	 * @return ICWP_WPSF_Processor_HackProtect_Ptg
	 */
	public function getSubProcessorPtg() {
		return $this->getSubPro( 'ptg' );
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Scanner\EntryVO $oEntry */
		$oEntry = $oMod->getDbHandler_ScanResults()
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

		if ( $this->getCanScansExecute() ) {
			$aScans = [];
			foreach ( $oOpts->getScanSlugs() as $sScanSlug ) {
				/** @var \ICWP_WPSF_Processor_ScanBase $oProc */
				$oProc = $this->getSubPro( $sScanSlug );
				if ( $oProc->isScanningAvailable() && $oProc->isEnabled() ) {
					$aScans[] = $sScanSlug;
				}
			}

			$oOpts->setIsScanCron( true );
			$oMod->saveModOptions()
				 ->getScanController()
				 ->startScans( $aScans );
		}
		else {
			error_log( 'Shield scans cannot execute.' );
		}
	}

	/**
	 * @return string[]
	 */
	public function getReasonsScansCantExecute() {
		return array_keys( array_filter( [
			'reason_not_call_self' => !$this->getCon()->getModule_Plugin()->getCanSiteCallToItself()
		] ) );
	}

	/**
	 * @return bool
	 */
	public function getCanScansExecute() {
		return count( $this->getReasonsScansCantExecute() ) === 0;
	}

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getScanFrequency();
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		return $this->getCon()->prefix( $this->getOptions()->getDef( 'cron_all_scans' ) );
	}
}