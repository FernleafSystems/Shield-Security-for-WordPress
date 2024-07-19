<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib\Utility\CleanOutOldGuardFiles,
	Scan\Queue\CleanQueue,
	Scan\Queue\ProcessQueueWpcli,
	Scan\Results\Update
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\ReportToMalai;
use FernleafSystems\Wordpress\Services\Services;

class ScansController {

	use ExecOnce;
	use PluginControllerConsumer;
	use StandardCron;
	use PluginCronsConsumer;

	private $scanCons = [];

	private $scanResultsStatus;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_hack_protect', 'Y' )
			   && self::con()->db_con->scan_results->isReady()
			   && self::con()->db_con->scan_items->isReady();
	}

	protected function run() {
		foreach ( $this->getAllScanCons() as $scanCon ) {
			$scanCon->execute();
		}
		$this->setCustomCronSchedules();
		$this->setupCron();
		$this->setupCronHooks();
		$this->handlePostScanCron();
	}

	protected function setCustomCronSchedules() {
		$freq = (int)self::con()->opts->optGet( 'scan_frequency' );
		Services::WpCron()->addNewSchedule(
			self::con()->prefix( sprintf( 'per-day-%s', $freq ) ),
			[
				'interval' => \DAY_IN_SECONDS/$freq,
				'display'  => sprintf( __( '%s per day', 'wp-simple-firewall' ), $freq )
			]
		);
	}

	public function runHourlyCron() {
		( new CleanQueue() )->execute();
		( new ReportToMalai() )->run();
	}

	public function AFS() :Controller\Afs {
		return $this->getScanCon( Controller\Afs::SCAN_SLUG );
	}

	public function APC() :Controller\Apc {
		return $this->getScanCon( Controller\Apc::SCAN_SLUG );
	}

	public function WPV() :Controller\Wpv {
		return $this->getScanCon( Controller\Wpv::SCAN_SLUG );
	}

	/**
	 * @return Controller\Base[]|mixed
	 */
	public function getAllScanCons() :array {
		foreach ( $this->getScans() as $scan ) {
			if ( empty( $this->scanCons[ $scan::SCAN_SLUG ] ) ) {
				$this->scanCons[ $scan::SCAN_SLUG ] = new $scan();
			}
		}
		return $this->scanCons;
	}

	/**
	 * @return string[]
	 */
	public function getScanSlugs() :array {
		return \array_keys( $this->getAllScanCons() );
	}

	/**
	 * @return string[]|Controller\Base[]
	 */
	public function getScans() :array {
		return [
			Controller\Afs::class,
			Controller\Apc::class,
			Controller\Wpv::class,
		];
	}

	/**
	 * @return ?|Controller\Afs|Controller\Apc|Controller\Wpv|Controller\Base
	 */
	public function getScanCon( string $slug ) {
		return $this->getAllScanCons()[ $slug ] ?? null;
	}

	public function getScanResultsCount() :Results\Counts {
		return $this->scanResultsStatus ?? $this->scanResultsStatus = new Results\Counts();
	}

	private function handlePostScanCron() {
		add_action( self::con()->prefix( 'post_scan' ), function () {
			( new ReportToMalai() )->run();
			$this->runAutoRepair();
		} );
	}

	private function runAutoRepair() {
		foreach ( $this->getAllScanCons() as $scanCon ) {
			$scanCon->runCronAutoRepair();
			$scanCon->cleanStalesResults();
		}
	}

	public function runCron() {
		Services::WpGeneral()->getIfAutoUpdatesInstalled() ? $this->resetCron() : $this->cronScan();
	}

	private function cronScan() {
		if ( $this->getCanScansExecute() ) {
			self::con()->opts->optSet( 'is_scan_cron', true )->store();
			$this->startNewScans( $this->getAllScanCons() );
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
			$reasons = \array_keys( \array_filter( [
				'reason_not_call_self' => !self::con()->plugin->canSiteLoopback()
			] ) );
		}
		catch ( \Exception $e ) {
			$reasons = [];
		}
		return $reasons;
	}

	public function startNewScans( array $scans, bool $resetIgnored = false ) :bool {
		$toScan = [];
		foreach ( $scans as $slugOrCon ) {
			try {
				$scanCon = \is_string( $slugOrCon ) ? $this->getScanCon( $slugOrCon ) : $slugOrCon;
				if ( $scanCon instanceof Controller\Base && $scanCon->isReady() ) {
					$toScan[] = $scanCon->getSlug();
					if ( $resetIgnored ) {
						( new Update() )
							->setScanController( $scanCon )
							->clearIgnored();
					}
					self::con()->comps->scans->addRemoveScanToBuild( $scanCon->getSlug() );
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( !empty( $toScan ) ) {
			if ( Services::WpGeneral()->isWpCli() ) {
				( new ProcessQueueWpcli() )->execute();
			}
			else {
				self::con()->comps->scans_queue->getQueueBuilder()->dispatch();
			}
		}

		return !empty( $toScan );
	}

	public function getCanScansExecute() :bool {
		return \count( $this->getReasonsScansCantExecute() ) === 0;
	}

	public function getFirstRunTimestamp() :int {
		$defaultStart = \rand( 1, 7 );

		$startHour = (int)apply_filters( 'shield/scan_cron_start_hour', $defaultStart );
		$startMinute = (int)apply_filters( 'shield/scan_cron_start_minute', \rand( 0, 59 ) );
		if ( $startHour < 0 || $startHour > 23 ) {
			$startHour = $defaultStart;
		}
		if ( $startMinute < 1 || $startMinute > 59 ) {
			$startMinute = \rand( 1, 59 );
		}

		$c = Services::Request()->carbon( true );
		if ( $c->hour > $startHour ) {
			$c->addDay(); // Start on this hour, tomorrow
		}
		elseif ( $c->hour === $startHour ) {
			if ( $c->minute >= $startMinute ) {
				$c->addDay(); // Start on this minute, tomorrow
			}
		}

		return $c->hour( $startHour )
				 ->minute( $startMinute )
				 ->second( 0 )->timestamp;
	}

	public function addRemoveScanToBuild( string $scan, bool $addScan = true ) :void {
		$scans = $this->getScansToBuild();
		if ( $addScan ) {
			$scans[ $scan ] = Services::Request()->ts();
		}
		else {
			unset( $scans[ $scan ] );
		}
		$this->setScansToBuild( $scans );
	}

	/**
	 * @return int[] - keys are scan slugs
	 */
	public function getScansToBuild() :array {
		$toBuild = self::con()->opts->optGet( 'scans_to_build' );
		if ( !empty( $toBuild ) ) {
			$wasCount = \count( $toBuild );
			// We keep scans "to build" for no longer than a minute to prevent indefinite halting with failed Async HTTP.
			$toBuild = \array_filter( $toBuild,
				function ( $toBuildAt ) {
					return \is_int( $toBuildAt )
						   && Services::Request()->carbon()->subMinute()->timestamp < $toBuildAt;
				}
			);
			if ( $wasCount !== \count( $toBuild ) ) {
				$this->setScansToBuild( $toBuild );
			}
		}
		return $toBuild;
	}

	private function setScansToBuild( array $scans ) :void {
		self::con()
			->opts
			->optSet( 'scans_to_build', \array_intersect_key( $scans, \array_flip( $this->getScanSlugs() ) ) )
			->store();
	}

	protected function getCronFrequency() {
		return self::con()->opts->optGet( 'scan_frequency' );
	}

	protected function getCronName() :string {
		return self::con()->prefix( 'all-scans' );
	}

	public function runDailyCron() {
		$carbon = Services::Request()->carbon();
		if ( $carbon->isSunday() ) {
			( new FileScanOptimiser() )->cleanStaleHashesOlderThan( $carbon->subWeek()->timestamp );
		}
		( new CleanOutOldGuardFiles() )->execute();
	}
}