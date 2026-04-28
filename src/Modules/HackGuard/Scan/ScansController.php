<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\{
	ScanCreateException,
	ScanExistsException
};
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

	private array $scanCons = [];

	private ?Results\Counts $scanResultsStatus = null;

	protected function canRun() :bool {
		return self::con()->db_con->scan_results->isReady() && self::con()->db_con->scan_items->isReady();
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
	 * @return Controller\Afs[]|Controller\Apc[]|Controller\Wpv[]
	 */
	public function getAllScanCons() :array {
		foreach ( $this->getScans() as $scan ) {
			$this->scanCons[ $scan::SCAN_SLUG ] ??= new $scan();
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
	 * @return class-string<Controller\Base>[]
	 */
	public function getScans() :array {
		return [
			Controller\Afs::class,
			Controller\Apc::class,
			Controller\Wpv::class,
		];
	}

	/**
	 * @return Controller\Afs|Controller\Apc|Controller\Wpv|null
	 */
	public function getScanCon( string $slug ) {
		return $this->getAllScanCons()[ $slug ] ?? null;
	}

	public function getScanResultsCount() :Results\Counts {
		return $this->scanResultsStatus ??= new Results\Counts();
	}

	public function resetScanResultsCountMemoization() :void {
		$this->scanResultsStatus = null;
		self::con()->comps->site_query->clearMemoized();
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
			$result = $this->startNewScans( $this->getAllScanCons() );
			if ( $result->hasFailures() ) {
				error_log( $result->getFailureLogMessage() );
			}
		}
		else {
			error_log( sprintf( __( '%s scans cannot execute.', 'wp-simple-firewall' ), self::con()->labels->Name ) );
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

	public function startNewScans( array $scans, bool $resetIgnored = false ) :StartScansResult {
		$normalized = $this->normalizeStartScanSlugs( $scans );
		$result = StartScansResult::fromRequested( $normalized );

		if ( !$this->canStartScans( Services::WpGeneral()->isWpCli() ) ) {
			return $result->addFailures( $normalized, StartScansResult::REASON_SCAN_UNAVAILABLE );
		}

		foreach ( $normalized as $slug ) {
			$scanCon = $this->getScanCon( $slug );
			if ( !$scanCon instanceof Controller\Base ) {
				$result->addFailure( $slug, StartScansResult::REASON_UNKNOWN_SCAN );
				continue;
			}
			try {
				$isReady = $scanCon->isReady();
			}
			catch ( \Exception $e ) {
				$result->addFailure( $slug, StartScansResult::REASON_SCAN_UNAVAILABLE, $e->getMessage() );
				continue;
			}
			if ( !$isReady ) {
				$result->addFailure( $slug, StartScansResult::REASON_SCAN_UNAVAILABLE );
				continue;
			}

			try {
				$scan = ( new Init\CreateNewScan() )->run(
					$scanCon->getSlug(),
					'full',
					'',
					Services::WpGeneral()->isWpCli() ? 'cli' : ( self::con()->opts->optGet( 'is_scan_cron' ) ? 'cron' : 'manual' )
				);
				if ( !empty( $scan ) ) {
					$result->addStarted( $scanCon->getSlug(), (int)$scan->id );
				}
				if ( $resetIgnored ) {
					( new Update() )
						->setScanController( $scanCon )
						->clearIgnored();
				}
			}
			catch ( ScanExistsException $e ) {
				$result->addFailure( $slug, StartScansResult::REASON_ALREADY_EXISTS, $e->getMessage() );
			}
			catch ( ScanCreateException $e ) {
				$result->addFailure( $slug, StartScansResult::REASON_CREATE_FAILED, $e->getMessage() );
			}
			catch ( \Exception $e ) {
				$result->addFailure( $slug, StartScansResult::REASON_CREATE_FAILED, $e->getMessage() );
			}
		}

		if ( $result->hasStarted() ) {
			if ( Services::WpGeneral()->isWpCli() ) {
				( new ProcessQueueWpcli() )->execute();
			}
			else {
				self::con()->comps->scans_queue->getQueueBuilder()->dispatch();
			}
		}

		return $result;
	}

	public function startAfsAssetScan( string $assetType, string $assetKey, bool $resetIgnored = false ) :bool {
		if ( !$this->canStartScans( Services::WpGeneral()->isWpCli() ) ) {
			return false;
		}

		$assetType = \in_array( $assetType, [ 'plugin', 'theme' ], true ) ? $assetType : '';
		$assetKey = trim( $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return false;
		}

		try {
			$scanCon = $this->AFS();
			if ( !$scanCon->isReady() ) {
				return false;
			}

			( new Init\CreateNewScan() )->run(
				$scanCon->getSlug(),
				$assetType,
				$assetKey,
				'asset_change'
			);

			if ( $resetIgnored ) {
				( new Update() )
					->setScanController( $scanCon )
					->clearIgnoredWithinScope( $assetType, $assetKey );
			}
		}
		catch ( \Exception $e ) {
			return false;
		}

		if ( Services::WpGeneral()->isWpCli() ) {
			( new ProcessQueueWpcli() )->execute();
		}
		else {
			self::con()->comps->scans_queue->getQueueBuilder()->dispatch();
		}

		return true;
	}

	public function getCanScansExecute() :bool {
		return \count( $this->getReasonsScansCantExecute() ) === 0;
	}

	private function normalizeStartScanSlugs( array $scans ) :array {
		$slugs = [];
		foreach ( $scans as $slugOrCon ) {
			$slug = $slugOrCon instanceof Controller\Base ? $slugOrCon->getSlug() : ( \is_string( $slugOrCon ) ? $slugOrCon : '' );
			$slug = trim( $slug );
			if ( $slug !== '' && !\in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	public function canStartScans( bool $isCli = false ) :bool {
		return $this->getStartBlockedMessage( $isCli ) === '';
	}

	public function getStartBlockedMessage( bool $isCli = false ) :string {
		if ( !$isCli && !$this->getCanScansExecute() ) {
			$reasons = $this->getReasonsScansCantExecute();
			if ( \in_array( 'reason_not_call_self', $reasons, true ) ) {
				return __( "Scans can't start because this site currently can't make HTTP requests to itself.", 'wp-simple-firewall' );
			}
			return __( 'Scans cannot execute right now.', 'wp-simple-firewall' );
		}
		return '';
	}

	public function getFirstRunTimestamp() :int {
		$defaultStart = wp_rand( 1, 7 );

		$startHour = (int)apply_filters( 'shield/scan_cron_start_hour', $defaultStart );
		$startMinute = (int)apply_filters( 'shield/scan_cron_start_minute', wp_rand( 0, 59 ) );
		if ( $startHour < 0 || $startHour > 23 ) {
			$startHour = $defaultStart;
		}
		if ( $startMinute < 1 || $startMinute > 59 ) {
			$startMinute = wp_rand( 1, 59 );
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

	protected function getCronFrequency() {
		return self::con()->opts->optGet( 'scan_frequency' );
	}

	protected function getCronName() :string {
		return self::con()->prefix( 'all-scans' );
	}

	public function runDailyCron() {
		$carbon = Services::Request()->carbon();
		( new FileScanOptimiser() )->cleanStaleHashesOlderThan( $carbon->subWeek()->timestamp );
		( new CleanOutOldGuardFiles() )->execute();
	}
}
