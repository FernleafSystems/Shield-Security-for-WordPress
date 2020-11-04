<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Scanner extends BaseShield\ShieldProcessor {

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

	public function getSubProcessorPtg() :\ICWP_WPSF_Processor_HackProtect_Ptg {
		return $this->getSubPro( 'ptg' );
	}

	protected function getSubProMap() :array {
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
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $sSlug ) {
			$oScanCon = $mod->getScanCon( $sSlug );
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
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		if ( $this->getCanScansExecute() ) {
			$aScans = [];
			foreach ( $opts->getScanSlugs() as $sScanSlug ) {
				$oScanCon = $mod->getScanCon( $sScanSlug );
				if ( $oScanCon->isScanningAvailable() && $oScanCon->isEnabled() ) {
					$aScans[] = $sScanSlug;
				}
			}

			$opts->setIsScanCron( true );
			$mod->saveModOptions()
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
	public function getReasonsScansCantExecute() :array {
		return array_keys( array_filter( [
			'reason_not_call_self' => !$this->getCon()->getModule_Plugin()->getCanSiteCallToItself()
		] ) );
	}

	public function getCanScansExecute() :bool {
		return count( $this->getReasonsScansCantExecute() ) === 0;
	}

	protected function getCronFrequency() {
		/** @var Shield\Modules\HackGuard\Options $opts */
		$opts = $this->getOptions();
		return $opts->getScanFrequency();
	}

	public function getFirstRunTimestamp() :int {
		$c = Services::Request()->carbon( true );
		$c->addHours( $c->minute < 40 ? 0 : 1 )
		  ->minute( $c->minute < 40 ? 45 : 15 )
		  ->second( 0 );

		if ( $this->getCronFrequency() === 1 ) { // If it's a daily scan only, set to 3am by default
			$hour = (int)apply_filters( $this->getCon()->prefix( 'daily_scan_cron_hour' ), 3 );
			if ( $hour < 0 || $hour > 23 ) {
				$hour = 3;
			}
			if ( $c->hour >= $hour ) {
				$c->addDays( 1 );
			}
			$c->hour( $hour );
		}

		return $c->timestamp;
	}

	protected function getCronName() :string {
		return $this->getCon()->prefix( $this->getOptions()->getDef( 'cron_all_scans' ) );
	}
}