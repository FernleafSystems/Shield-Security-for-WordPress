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
	public function getScansRunningStates() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		/** @var ScanQueue\Select $sel */
		$sel = $mod->getDbHandler_ScanQueue()->getQuerySelector();

		// First clean the queue:
		$this->cleanExpiredFromQueue();

		$aScans = array_fill_keys( $opts->getScanSlugs(), false );
		foreach ( $sel->getInitiatedScans() as $sInitScan ) {
			$aScans[ $sInitScan ] = true;
		}
		return $aScans;
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
		/** @var ScanQueue\Delete $oDel */
		$oDel = $mod->getDbHandler_ScanQueue()->getQueryDeleter();
		return $oDel->addWhereOlderThan( $nExpiredBoundary )
					->query();
	}

	/**
	 * @return string[]
	 */
	public function getRunningScans() {
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

		$aUnfinished = $sel->getUnfinishedScans();
		$nProgress = 1;
		if ( $sel->getUnfinishedScans() > 0 ) {
			$nInitiated = count( $sel->getInitiatedScans() );
			if ( $nInitiated > 0 ) {
				$nProgress = 1 - ( count( $aUnfinished )/count( $sel->getInitiatedScans() ) );
			}
		}
		else {
			$nProgress = 1;
		}
		return $nProgress;
	}

	/**
	 * @return bool
	 */
	public function hasRunningScans() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return count( $this->getRunningScans() ) > 0 || count( $oOpts->getScansToBuild() ) > 0;
	}

	/**
	 * @param string|string[] $aScanSlugs
	 */
	public function startScans( $aScanSlugs ) {
		if ( !is_array( $aScanSlugs ) ) {
			$aScanSlugs = [ $aScanSlugs ];
		}
		if ( !empty( $aScanSlugs ) ) {
			/** @var HackGuard\Options $oOpts */
			$oOpts = $this->getOptions();
			foreach ( $aScanSlugs as $sSlug ) {
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
