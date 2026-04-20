<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\FindingsModel\{
	LegacyReconcile,
	LegacyReconcileQueue,
	State as FindingsModelState
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Controller {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var Build\QueueBuilder
	 */
	private $queueBuilder;

	/**
	 * @var QueueProcessor
	 */
	private $queueProcessor;

	/**
	 * @var LegacyReconcileQueue
	 */
	private $reconcileQueue;

	protected function run() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() {
		$this->maybeRunFindingsReconcile();

		if ( $this->hasRunningScans()
			 || ( self::con()->isPluginAdminPageRequest() && PluginNavs::GetNav() === PluginNavs::NAV_SCANS ) ) {
			$this->getQueueBuilder();
			$this->getQueueProcessor();
		}
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
	public function getScanJobProgress() {
		/** @var ScanItemsDB\Select $selector */
		$selector = self::con()->db_con->scan_items->getQuerySelector();

		$countsAll = $selector->countAllForEachScan();
		$countsUnfinished = $selector->countUnfinishedForEachScan();

		if ( empty( $countsAll ) || empty( $countsUnfinished ) ) {
			$progress = 1;
		}
		else {
			$progress = 0;
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
				   ->addWhereIn( 'status', [ 'queued', 'building', 'running' ] )
				   ->count() > 0;
	}

	public function getReconcileQueue() :LegacyReconcileQueue {
		return $this->reconcileQueue ?? $this->reconcileQueue = new LegacyReconcileQueue();
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		return $this->queueBuilder ?? $this->queueBuilder = new Build\QueueBuilder( 'shield_scanqbuild' );
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->queueProcessor ?? $this->queueProcessor = ( new QueueProcessor( 'shield_scanq' ) )->setExpirationInterval( \MINUTE_IN_SECONDS*10 );
	}

	private function maybeRunFindingsReconcile() :void {
		$state = new FindingsModelState();
		if ( $state->current() === FindingsModelState::LEGACY ) {
			$state->startReconciling();
		}

		if ( !$state->isReconciling() ) {
			return;
		}

		if ( !$state->hasLegacyRows() ) {
			$state->markReady();
			return;
		}

		$queue = $this->getReconcileQueue();
		if ( self::con()->plugin->canSiteLoopback() ) {
			if ( !$queue->is_processing() ) {
				$queue->dispatch();
			}
		}
		elseif ( self::con()->isValidAdminArea() ) {
			( new LegacyReconcile() )->processBatch();
		}
	}
}
