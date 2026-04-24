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
	public function getScansRunningStates() :array {
		$scans = \array_fill_keys( self::con()->comps->scans->getScanSlugs(), false );
		foreach ( ( new ScansStatus() )->enqueued() as $enqueued ) {
			$scans[ $enqueued ] = true;
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

		$countsAll = $selector->countAllForEachScan();
		$countsUnfinished = $selector->countUnfinishedForEachScan();

		if ( empty( $countsAll ) || empty( $countsUnfinished ) ) {
			$progress = 1.0;
		}
		else {
			$progress = 0.0;
			$eachScanWeight = 1/count( $countsAll );
			foreach ( \array_keys( $countsAll ) as $scan ) {
				$progress += $eachScanWeight*( 1 - ( ( $countsUnfinished[ $scan ] ?? 0 )/$countsAll[ $scan ] ) );
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
