<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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

	protected function run() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() {
		$this->getQueueBuilder();
		$this->getQueueProcessor();

		if ( $this->hasRunningScans()
			 || ( self::con()->isPluginAdminPageRequest() && PluginNavs::GetNav() === PluginNavs::NAV_SCANS ) ) {
			$this->maybeRedispatchQueues();
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
				   ->addWhereIn( 'status', [ 'queued', 'building', 'built', 'running' ] )
				   ->count() > 0;
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		return $this->queueBuilder ?? $this->queueBuilder = new Build\QueueBuilder();
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->queueProcessor ?? $this->queueProcessor = new QueueProcessor();
	}

	private function hasReadyScanWork() :bool {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByNotFinished()
				   ->filterByReady()
				   ->addWhereIn( 'status', [ 'built', 'running' ] )
				   ->count() > 0;
	}

	private function hasQueuedScans() :bool {
		return self::con()->db_con->scans->getQuerySelector()
				   ->filterByStatus( 'queued' )
				   ->filterByNotFinished()
				   ->count() > 0;
	}

	private function maybeRedispatchQueues() :void {
		$builder = $this->getQueueBuilder();
		$processor = $this->getQueueProcessor();

		if ( $this->hasQueuedScans() && !$builder->is_processing() ) {
			$builder->dispatch();
		}
		if ( $this->hasReadyScanWork() && !$processor->is_processing() ) {
			$processor->dispatch();
		}
	}
}
