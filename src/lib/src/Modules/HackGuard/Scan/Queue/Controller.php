<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;

class Controller {

	use ModConsumer;

	/**
	 * @var Build\QueueBuilder
	 */
	private $queueBuilder;

	/**
	 * @var QueueProcessor
	 */
	private $queueProcessor;

	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() {
		$this->getQueueBuilder();
		$this->getQueueProcessor();
	}

	/**
	 * @return bool[]
	 */
	public function getScansRunningStates() :array {
		$scans = \array_fill_keys( $this->mod()->getScansCon()->getScanSlugs(), false );
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
		/** @var ScanItemsDB\Ops\Select $selector */
		$selector = $this->mod()->getDbH_ScanItems()->getQuerySelector();

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
		return \count( $this->getRunningScans() ) > 0 || \count( $this->opts()->getScansToBuild() ) > 0;
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		return $this->queueBuilder ?? $this->queueBuilder = new Build\QueueBuilder( 'shield_scanqbuild' );
	}

	public function getQueueProcessor() :QueueProcessor {
		return $this->queueProcessor ?? $this->queueProcessor = ( new QueueProcessor( 'shield_scanq' ) )->setExpirationInterval( \MINUTE_IN_SECONDS*10 );
	}
}
