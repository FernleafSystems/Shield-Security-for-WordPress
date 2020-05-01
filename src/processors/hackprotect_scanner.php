<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Scanner extends ShieldProcessor {

	use Shield\Crons\StandardCron;

	public function run() {
		$this->getSubPro( 'apc' )->execute();
		$this->getSubPro( 'ufc' )->execute();
		$this->getSubPro( 'wcf' )->execute();
		$this->getSubPro( 'ptg' )->execute();
		if ( $this->getCon()->isPremiumActive() ) {
			$this->getSubPro( 'mal' )->execute();
			$this->getSubPro( 'wpv' )->execute();
		}
		$this->setupCron();
		$this->handlePostScanCron();
	}

	/**
	 * @return \ICWP_WPSF_Processor_HackProtect_Ptg
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
			'mal' => 'ICWP_WPSF_Processor_HackProtect_Mal',
			'ptg' => 'ICWP_WPSF_Processor_HackProtect_Ptg',
			'ufc' => 'ICWP_WPSF_Processor_HackProtect_Ufc',
			'wcf' => 'ICWP_WPSF_Processor_HackProtect_Wcf',
			'wpv' => 'ICWP_WPSF_Processor_HackProtect_Wpv',
		];
	}

	private function handlePostScanCron() {
		add_action( $this->getCon()->prefix( 'post_scan' ), function () {
			$this->runAutoRepair();
		} );
	}

	private function runAutoRepair() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		foreach ( $oOpts->getScanSlugs() as $sSlug ) {
			$oScanCon = $oMod->getScanCon( $sSlug );
			if ( $oScanCon->isCronAutoRepair() ) {
				$oScanCon->runCronAutoRepair();
			}
		}
	}

	public function runHourlyCron() {
		( new HackGuard\Lib\Snapshots\StoreAction\TouchAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function runDailyCron() {
		( new HackGuard\Lib\Snapshots\StoreAction\CleanAll() )
			->setMod( $this->getMod() )
			->run();
	}

	public function onWpLoaded() {
		( new HackGuard\Lib\Snapshots\StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->hookBuild();
	}

	public function onModuleShutdown() {
		parent::onModuleShutdown();
		( new HackGuard\Lib\Snapshots\StoreAction\ScheduleBuildAll() )
			->setMod( $this->getMod() )
			->schedule();
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
				$oScanCon = $oMod->getScanCon( $sScanSlug );
				if ( $oScanCon->isScanningAvailable() && $oScanCon->isEnabled() ) {
					$aScans[] = $sScanSlug;
				}
			}

			$oOpts->setIsScanCron( true );
			$oMod->saveModOptions()
				 ->getScanQueueController()
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
	public function getFirstRunTimestamp() {
		$oCarb = Services::Request()->carbon( true );
		$oCarb->addHours( $oCarb->minute < 40 ? 0 : 1 )
			  ->minute( $oCarb->minute < 40 ? 45 : 15 )
			  ->second( 0 );

		if ( $this->getCronFrequency() === 1 ) { // If it's a daily scan only, set to 3am by default
			$nHour = (int)apply_filters( $this->getCon()->prefix( 'daily_scan_cron_hour' ), 3 );
			if ( $nHour < 0 || $nHour > 23 ) {
				$nHour = 3;
			}
			if ( $oCarb->hour >= $nHour ) {
				$oCarb->addDays( 1 );
			}
			$oCarb->hour( $nHour );
		}

		return $oCarb->timestamp;
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		return $this->getCon()->prefix( $this->getOptions()->getDef( 'cron_all_scans' ) );
	}
}