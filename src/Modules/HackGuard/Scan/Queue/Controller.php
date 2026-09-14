<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-import-type ActiveScanRow from ScansStatus
 * @phpstan-import-type ActiveScanStatus from ScansStatus
 * @phpstan-type ActiveScanProgressRow array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:ActiveScanStatus,display_status:'running'|'waiting'|'stalled',is_current:bool,is_stale:bool,can_attempt_recovery:bool,progress:int,total_items:int,unfinished:int}
 */
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
	 * @param ?list<string> $enqueued
	 * @return array<string,bool>
	 */
	public function getScansRunningStates( ?array $enqueued = null ) :array {
		$scans = \array_fill_keys( self::con()->comps->scans->getScanSlugs(), false );
		foreach ( $enqueued ?? ( new ScansStatus() )->enqueued() as $scan ) {
			$scans[ $scan ] = true;
		}
		return $scans;
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
				$total = $counts[ 'total' ];
				if ( $total > 0 ) {
					$progress += $eachScanWeight*( 1 - ( $counts[ 'unfinished' ]/$total ) );
				}
			}
		}

		return $progress;
	}

	/**
	 * @param list<ActiveScanRow> $activeScans
	 * @return list<ActiveScanProgressRow>
	 */
	public function getActiveScanProgressRows( array $activeScans ) :array {
		if ( empty( $activeScans ) ) {
			return [];
		}

		$progressCounts = $this->getScanProgressCounts();
		$currentScanID = $activeScans[ 0 ][ 'id' ];
		$cutoff = Services::Request()->ts() - QueueWatchdog::STALE_AFTER;

		$rows = [];
		foreach ( $activeScans as $activeScan ) {
			$scanID = $activeScan[ 'id' ];
			$counts = $progressCounts[ $scanID ] ?? [
				'total'      => 0,
				'unfinished' => 0,
			];
			$total = $counts[ 'total' ];
			$unfinished = $counts[ 'unfinished' ];
			$isCurrent = $scanID === $currentScanID;
			$isActionableStale = $isCurrent && $this->getQueueWatchdog()->isActiveScanStale( $activeScan, $cutoff );
			$progress = $total > 0 ? (int)\round( 100*( 1 - ( $unfinished/$total ) ) ) : 0;
			$progress = (int)\max( 0, \min( 100, $progress ) );

			$rows[] = [
				'id'                   => $scanID,
				'scan'                 => $activeScan[ 'scan' ],
				'name'                 => $this->scanName( $activeScan[ 'scan' ] ),
				'scope_type'           => $activeScan[ 'scope_type' ],
				'scope_key'            => $activeScan[ 'scope_key' ],
				'raw_status'           => $activeScan[ 'status' ],
				'display_status'       => $isActionableStale ? 'stalled' : ( $isCurrent ? 'running' : 'waiting' ),
				'is_current'           => $isCurrent,
				'is_stale'             => $isActionableStale,
				'can_attempt_recovery' => $isActionableStale,
				'progress'             => $isCurrent ? $progress : 0,
				'total_items'          => $total,
				'unfinished'           => $unfinished,
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
	 * @return array<int,array{total:int,unfinished:int}>
	 */
	private function getScanProgressCounts() :array {
		/** @var ScanItemsDB\Select $selector */
		$selector = self::con()->db_con->scan_items->getQuerySelector();
		return $selector->countProgressForEachScan();
	}

	private function scanName( string $scan ) :string {
		$scanCon = self::con()->comps->scans->getScanCon( $scan );
		return $scanCon === null ? $scan : $scanCon->getScanName();
	}
}
