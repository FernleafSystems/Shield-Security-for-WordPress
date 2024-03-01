<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
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

	private $scanCons = [];

	private $scanResultsStatus;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_hack_protect', 'Y' )
			   && self::con()->db_con->dbhScanResults()->isReady()
			   && self::con()->db_con->dbhScanItems()->isReady();
	}

	protected function run() {
		foreach ( $this->getAllScanCons() as $scanCon ) {
			$scanCon->execute();
		}
		$this->mod()->getFileLocker()->execute();

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
		if ( $this->getCanScansExecute() && \method_exists( self::con()->opts, 'optSet' ) ) {
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
					if ( self::con()->comps === null ) {
						$this->opts()->addRemoveScanToBuild( $scanCon->getSlug() );
					}
					else {
						self::con()->comps->scans->addRemoveScanToBuild( $scanCon->getSlug() );
					}
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
		$this->setOpt( 'scans_to_build',
			\array_intersect_key( $scans,
				\array_flip( self::con()->getModule_HackGuard()->getScansCon()->getScanSlugs() )
			)
		);
		self::con()->opts->store();
	}

	protected function getCronFrequency() {
		return $this->opts()->getOpt( 'scan_frequency', 1 );
	}

	protected function getCronName() :string {
		return self::con()->prefix( 'all-scans' );
	}
}