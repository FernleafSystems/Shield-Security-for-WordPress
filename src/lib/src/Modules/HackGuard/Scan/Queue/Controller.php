<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Controller
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue
 */
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
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		/** @var ScanQueue\Select $sel */
		$sel = $mod->getDbHandler_ScanQueue()->getQuerySelector();

		// First clean the queue:
		$this->cleanExpiredFromQueue();

		$scans = array_fill_keys( $opts->getScanSlugs(), false );
		foreach ( $sel->getInitiatedScans() as $sInitScan ) {
			$scans[ $sInitScan ] = true;
		}
		return $scans;
	}

	/**
	 * @return bool
	 */
	protected function cleanExpiredFromQueue() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		$nExpiredBoundary = Services::Request()
									->carbon()
									->subSeconds( $opts->getMalQueueExpirationInterval() )->timestamp;
		/** @var ScanQueue\Delete $deleter */
		$deleter = $mod->getDbHandler_ScanQueue()->getQueryDeleter();
		return $deleter->addWhereOlderThan( $nExpiredBoundary )
					   ->query();
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
		/** @var ScanQueue\Select $sel */
		$sel = $mod->getDbHandler_ScanQueue()->getQuerySelector();

		$countsAll = $sel->countAllForEachScan();
		$countsUnfinished = $sel->countUnfinishedForEachScan();

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
			/** @var HackGuard\Options $oOpts */
			$oOpts = $this->getOptions();
			foreach ( $scanSlugs as $sSlug ) {
				$oOpts->addRemoveScanToBuild( $sSlug );
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
