<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Controller {

	use ExecOnce;
	use PluginControllerConsumer;

	private ?Build\QueueBuilder $queueBuilder = null;

	private ?QueueProcessor $queueProcessor = null;

	protected function run() :void {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() :void {
		$this->getQueueBuilder();
		$this->getQueueProcessor();
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
		/** @var ScanItemsDB\Select $selector */
		$selector = self::con()->db_con->scan_items->getQuerySelector();

		$progressCounts = $selector->countProgressForEachScan();

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

	public function hasRunningScans() :bool {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByNotFinished()
				   ->addWhereIn( 'status', [ 'queued', 'building', 'built', 'running' ] )
				   ->count() > 0;
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		return $this->queueBuilder ??= new Build\QueueBuilder();
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->queueProcessor ??= new QueueProcessor();
	}
}
