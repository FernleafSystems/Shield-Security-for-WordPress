<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Options,
	Scan\Queue\CompleteQueue,
	Scan\Queue\ProcessQueueItem,
	Scan\Queue\ProcessQueueWpcli,
	Scan\Queue\QueueInit,
	Scan\Queue\QueueItems
};
use FernleafSystems\Wordpress\Services\Services;

class ScansController extends ExecOnceModConsumer {

	use StandardCron;
	use PluginCronsConsumer;

	private $scanCons;

	private $scanResultsStatus;

	public function __construct() {
		$this->scanCons = [];
	}

	protected function run() {
		foreach ( $this->getAllScanCons() as $scanCon ) {
			$scanCon->execute();
		}
		$this->setupCron();
		$this->setupCronHooks(); // Plugin crons
		$this->handlePostScanCron();
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

	public function getScanResultsCount() :Results\Counts {
		if ( !isset( $this->scanResultsStatus ) ) {
			$this->scanResultsStatus = ( new Results\Counts() )
				->setMod( $this->getMod() );
		}
		return $this->scanResultsStatus;
	}

	private function handlePostScanCron() {
		add_action( $this->getCon()->prefix( 'post_scan' ), function () {
			$this->runAutoRepair();
		} );
	}

	private function runAutoRepair() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		foreach ( $opts->getScanSlugs() as $slug ) {
			$scanCon = $mod->getScanCon( $slug );
			$scanCon->runCronAutoRepair();
			$scanCon->cleanStalesResults();
		}
	}

	/**
	 * Cron callback
	 */
	public function runCron() {
		Services::WpGeneral()->getIfAutoUpdatesInstalled() ? $this->resetCron() : $this->cronScan();
	}

	private function cronScan() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $this->getCanScansExecute() ) {
			$scans = [];
			foreach ( $opts->getScanSlugs() as $slug ) {
				$scanCon = $mod->getScanCon( $slug );
				if ( $scanCon->isReady() ) {
					$scans[] = $slug;
				}
			}

			$opts->setIsScanCron( true );
			$mod->saveModOptions()
				->getScansCon()
				->startNewScans( $scans );
		}
		else {
			error_log( 'Shield scans cannot execute.' );
		}
	}

	/**
	 * @return string[]
	 */
	public function getReasonsScansCantExecute() :array {
		try {
			$reasons = array_keys( array_filter( [
				'reason_not_call_self' => !$this->getCon()->getModule_Plugin()->canSiteLoopback()
			] ) );
		}
		catch ( \Exception $e ) {
			$reasons = [];
		}
		return $reasons;
	}

	public function startNewScans( array $scans, bool $resetIgnored = false ) :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$toScan = [];
		foreach ( $scans as $slug ) {
			try {
				$thisScanCon = $this->getScanCon( $slug );
				if ( $thisScanCon->isReady() ) {
					$toScan[] = $slug;
					if ( $resetIgnored ) {
						$thisScanCon->resetIgnoreStatus();
					}
					$opts->addRemoveScanToBuild( $slug );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( !empty( $toScan ) ) {
			if ( Services::WpGeneral()->isWpCli() ) {
				( new ProcessQueueWpcli() )
					->setMod( $this->getMod() )
					->execute();
			}
			else {
				$mod->getScanQueueController()
					->getQueueBuilder()
					->dispatch();
			}
		}

		return !empty( $toScan );
	}

	public function getCanScansExecute() :bool {
		return count( $this->getReasonsScansCantExecute() ) === 0;
	}

	protected function getCronFrequency() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getScanFrequency();
	}

	public function getFirstRunTimestamp() :int {

		$startHour = (int)apply_filters( 'shield/scan_cron_start_hour', 3 );
		$startMinute = (int)apply_filters( 'shield/scan_cron_start_minute', (int)rand( 0, 59 ) );
		if ( $startHour < 0 || $startHour > 23 ) {
			$startHour = 3;
		}
		if ( $startMinute < 1 || $startMinute > 59 ) {
			$startMinute = (int)rand( 1, 59 );
		}

		$c = Services::Request()->carbon( true );
		if ( $c->hour > $startHour ) {
			$c->addDays( 1 ); // Start on this hour, tomorrow
		}
		elseif ( $c->hour === $startHour ) {
			if ( $c->minute >= $startMinute ) {
				$c->addDays( 1 ); // Start on this minute, tomorrow
			}
		}

		$c->hour( $startHour )
		  ->minute( $startMinute )
		  ->second( 0 );

		return $c->timestamp;
	}

	protected function getCronName() :string {
		return $this->getCon()->prefix( $this->getOptions()->getDef( 'cron_all_scans' ) );
	}
}