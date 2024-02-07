<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModConsumer,
	Scan\Queue\CleanQueue,
	Scan\Queue\ProcessQueueWpcli,
	Scan\Results\Update
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\ReportToMalai;
use FernleafSystems\Wordpress\Services\Services;

class ScansController {

	use ExecOnce;
	use ModConsumer;
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
		$this->setupCronHooks();
		$this->handlePostScanCron();
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
			$this->opts()->setIsScanCron( true );
			self::con()->opts->store();
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
				'reason_not_call_self' => !self::con()->getModule_Plugin()->canSiteLoopback()
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
					$this->opts()->addRemoveScanToBuild( $scanCon->getSlug() );
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
				$this->mod()
					 ->getScanQueueController()
					 ->getQueueBuilder()
					 ->dispatch();
			}
		}

		return !empty( $toScan );
	}

	public function getCanScansExecute() :bool {
		return \count( $this->getReasonsScansCantExecute() ) === 0;
	}

	protected function getCronFrequency() {
		return $this->opts()->getScanFrequency();
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

	protected function getCronName() :string {
		return self::con()->prefix( 'all-scans' );
	}
}