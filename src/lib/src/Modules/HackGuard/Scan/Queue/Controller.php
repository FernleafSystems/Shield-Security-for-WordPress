<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Controller {

	use ModConsumer;

	/**
	 * @var Build\QueueBuilder
	 */
	private $oQueueBuilder;

	/**
	 * @var QueueProcessor
	 */
	private $oQueueProcessor;

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
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$scans = array_fill_keys( $opts->getScanSlugs(), false );
		foreach ( ( new HackGuard\Scan\Init\ScansStatus() )->setMod( $this->getMod() )->enqueued() as $enqueued ) {
			$scans[ $enqueued ] = true;
		}
		return $scans;
	}

	/**
	 * @return string[]
	 */
	public function getRunningScans() :array {
		return array_keys( array_filter( $this->getScansRunningStates() ) );
	}

	/**
	 * @return float
	 */
	public function getScanJobProgress() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var ScanItemsDB\Ops\Select $selector */
		$selector = $mod->getDbH_ScanItems()->getQuerySelector();

		$countsAll = $selector->countAllForEachScan();
		$countsUnfinished = $selector->countUnfinishedForEachScan();

		if ( empty( $countsAll ) || empty( $countsUnfinished ) ) {
			$progress = 1;
		}
		else {
			$progress = 0;
			$eachScanWeight = 1/count( $countsAll );
			foreach ( array_keys( $countsAll ) as $scan ) {
				$progress += $eachScanWeight*( 1 - ( ( $countsUnfinished[ $scan ] ?? 0 )/$countsAll[ $scan ] ) );
			}
		}

		return $progress;
	}

	public function hasRunningScans() :bool {
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		return count( $this->getRunningScans() ) > 0 || count( $opts->getScansToBuild() ) > 0;
	}

	/**
	 * @param string|string[] $scanSlugs
	 */
	public function startScans( $scanSlugs ) {
		if ( !is_array( $scanSlugs ) ) {
			$scanSlugs = [ $scanSlugs ];
		}
		if ( !empty( $scanSlugs ) ) {
			/** @var HackGuard\Options $opts */
			$opts = $this->getOptions();
			foreach ( $scanSlugs as $slug ) {
				$opts->addRemoveScanToBuild( $slug );
			}
			$this->getQueueBuilder()->dispatch();
		}
	}

	/**
	 * @return Build\QueueBuilder
	 */
	public function getQueueBuilder() {
		if ( empty( $this->oQueueBuilder ) ) {
			$this->oQueueBuilder = ( new Build\QueueBuilder( 'shield_scanqbuild' ) )
				->setMod( $this->getMod() )
				->setQueueProcessor( $this->getQueueProcessor() );
		}
		return $this->oQueueBuilder;
	}

	/**
	 * @return QueueProcessor
	 */
	public function getQueueProcessor() {
		if ( empty( $this->oQueueProcessor ) ) {
			/** @var HackGuard\Options $oOpts */
			$oOpts = $this->getOptions();
			$this->oQueueProcessor = ( new QueueProcessor( 'shield_scanq' ) )
				->setMod( $this->getMod() )
				->setExpirationInterval( $oOpts->getMalQueueExpirationInterval() );
		}
		return $this->oQueueProcessor;
	}
}
