<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Controller {

	use ExecOnce;
	use PluginControllerConsumer;

	private ?Build\QueueBuilder $queueBuilder = null;

	private ?QueueProcessor $queueProcessor = null;

	private ?QueueWatchdog $queueWatchdog = null;

	protected function run() :void {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() :void {
		$this->getQueueBuilder();
		$this->getQueueProcessor();
		$this->getQueueWatchdog()->register();
	}

	/**
	 * @return bool[]
	 */
	public function getScansRunningStates( ?array $enqueued = null ) :array {
		$scans = \array_fill_keys( self::con()->comps->scans->getScanSlugs(), false );
		foreach ( $enqueued ?? ( new ScansStatus() )->enqueued() as $scan ) {
			$scans[ $scan ] = true;
		}
		return $scans;
	}

	/**
	 * @return string[]
	 */
	public function getRunningScans() :array {
		return \array_keys( \array_filter( $this->getScansRunningStates() ) );
	}

	/**
	 * @return float
	 */
	public function getScanJobProgress() :float {
		$progressCounts = $this->getScanProgressCounts();

		if ( empty( $progressCounts ) ) {
			$progress = 1.0;
		}
		else {
			$progress = 0.0;
			$eachScanWeight = 1/count( $progressCounts );
			foreach ( $progressCounts as $counts ) {
				$total = (int)$counts[ 'total' ];
				if ( $total > 0 ) {
					$progress += $eachScanWeight*( 1 - ( ( (int)$counts[ 'unfinished' ] )/$total ) );
				}
			}
		}

		return $progress;
	}

	/**
	 * @param list<array{id:int,scan:string,status:string,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int}> $activeScans
	 * @return list<array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:string,display_status:string,is_current:bool,is_stale:bool,progress:int,total_items:int,unfinished:int}>
	 */
	public function getActiveScanProgressRows( array $activeScans ) :array {
		if ( empty( $activeScans ) ) {
			return [];
		}

		$progressCounts = $this->getScanProgressCounts();
		$currentScanID = (int)$activeScans[ 0 ][ 'id' ];
		$cutoff = Services::Request()->ts() - QueueWatchdog::STALE_AFTER;

		$rows = [];
		foreach ( $activeScans as $activeScan ) {
			$scanID = (int)$activeScan[ 'id' ];
			$counts = $progressCounts[ $scanID ] ?? [
				'total'      => 0,
				'unfinished' => 0,
			];
			$total = \max( 0, (int)$counts[ 'total' ] );
			$unfinished = \max( 0, (int)$counts[ 'unfinished' ] );
			$isCurrent = $scanID === $currentScanID;
			$isStale = $this->isStaleActiveScan( $activeScan, $cutoff );
			$progress = $total > 0 ? (int)\round( 100*( 1 - ( $unfinished/$total ) ) ) : 0;
			$progress = (int)\max( 0, \min( 100, $progress ) );

			$rows[] = [
				'id'             => $scanID,
				'scan'           => $activeScan[ 'scan' ],
				'name'           => $this->scanName( $activeScan[ 'scan' ] ),
				'scope_type'     => $activeScan[ 'scope_type' ],
				'scope_key'      => $activeScan[ 'scope_key' ],
				'raw_status'     => $activeScan[ 'status' ],
				'display_status' => $isStale ? 'stalled' : ( $isCurrent ? 'running' : 'waiting' ),
				'is_current'     => $isCurrent,
				'is_stale'       => $isStale,
				'progress'       => $isCurrent || $isStale ? $progress : 0,
				'total_items'    => $total,
				'unfinished'     => $unfinished,
			];
		}

		return $rows;
	}

	public function hasRunningScans() :bool {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByNotFinished()
				   ->addWhereIn( 'status', ScanStatus::ACTIVE )
				   ->count() > 0;
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		return $this->queueBuilder ??= new Build\QueueBuilder();
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->queueProcessor ??= new QueueProcessor();
	}

	public function getQueueWatchdog() :QueueWatchdog {
		return $this->queueWatchdog ??= new QueueWatchdog();
	}

	/**
	 * @return array<int|string,array{total:int,unfinished:int}>
	 */
	private function getScanProgressCounts() :array {
		/** @var ScanItemsDB\Select $selector */
		$selector = self::con()->db_con->scan_items->getQuerySelector();
		return $selector->countProgressForEachScan();
	}

	/**
	 * @param array{id:int,scan:string,status:string,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int} $scan
	 */
	private function isStaleActiveScan( array $scan, int $cutoff ) :bool {
		switch ( $scan[ 'status' ] ) {
			case ScanStatus::QUEUED:
			case ScanStatus::BUILDING:
				return $this->isStaleTimestamp( $scan[ 'last_process_at' ], $scan[ 'created_at' ], $cutoff );

			case ScanStatus::BUILT:
				return $scan[ 'ready_at' ] > 0
					   && $this->isStaleTimestamp( $scan[ 'last_process_at' ], $scan[ 'ready_at' ], $cutoff );

			case ScanStatus::RUNNING:
				return $scan[ 'ready_at' ] > 0
					   && $this->isStaleTimestamp( $scan[ 'last_process_at' ], $scan[ 'created_at' ], $cutoff );

			default:
				return false;
		}
	}

	private function isStaleTimestamp( int $lastProcessAt, int $fallbackAt, int $cutoff ) :bool {
		return $lastProcessAt > 0 ? $lastProcessAt < $cutoff : $fallbackAt < $cutoff;
	}

	private function scanName( string $scan ) :string {
		$scanCon = self::con()->comps->scans->getScanCon( $scan );
		return \is_object( $scanCon ) && \method_exists( $scanCon, 'getScanName' )
			? (string)$scanCon->getScanName()
			: $scan;
	}
}
