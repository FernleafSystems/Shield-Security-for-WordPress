<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_HackProtect_Scanner extends BaseShield\ShieldProcessor {

	use Shield\Crons\StandardCron;

	public function run() {
	}

	public function getSubProcessorPtg() :\ICWP_WPSF_Processor_HackProtect_Ptg {
		return $this->getSubPro( 'ptg' );
	}

	protected function getSubProMap() :array {
		return [];
	}

	private function handlePostScanCron() {
	}

	private function runAutoRepair() {
	}

	public function runHourlyCron() {
	}

	public function runDailyCron() {
	}

	public function onWpLoaded() {
	}

	public function onModuleShutdown() {
	}

	/**
	 * Cron callback
	 */
	public function runCron() {
	}

	private function cronScan() {
	}

	public function getReasonsScansCantExecute() :array {
		return [];
	}

	public function getCanScansExecute() :bool {
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