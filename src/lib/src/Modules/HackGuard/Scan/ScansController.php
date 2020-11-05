<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ScansController {

	use ModConsumer;
	use OneTimeExecute;
	use StandardCron;

	private $scanCons;

	public function __construct() {
		$this->scanCons = [];
	}

	protected function run() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		foreach ( $this->getAllScanCons() as $scanCon ) {
			$scanCon->execute();
		}
		$this->setupCron();
		$this->handlePostScanCron();
		add_action( $mod->prefix( 'plugin_shutdown' ), [ $this, 'onModuleShutdown' ] );
		add_action( $mod->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $mod->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}

	/**
	 * @return Controller\Base[]
	 */
	public function getAllScanCons() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $slug ) {
			try {
				$this->getScanCon( $slug );
			}
			catch ( \Exception $e ) {
			}
		}
		return $this->scanCons;
	}

	/**
	 * @param string $slug
	 * @return Controller\Base|mixed
	 * @throws \Exception
	 */
	public function getScanCon( string $slug ) {
		if ( !isset( $this->scanCons[ $slug ] ) ) {
			$class = __NAMESPACE__.'\\Controller\\'.ucwords( $slug );
			if ( @class_exists( $class ) ) {
				/** @var Controller\Base $obj */
				$obj = new $class();
				$this->scanCons[ $slug ] = $obj->setMod( $this->getMod() );
			}
			else {
				throw new \Exception( 'Scan slug does not have a class: '.$slug );
			}
		}
		return $this->scanCons[ $slug ];
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
		/** @var HackGuard\Options $opts */
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