<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

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
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$scans = array_fill_keys( $mod->getScansCon()->getScanSlugs(), false );
		foreach ( ( new ScansStatus() )->setMod( $this->getMod() )->enqueued() as $enqueued ) {
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
		/** @var ModCon $mod */
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
		/** @var Options $opts */
		$opts = $this->getOptions();
		return count( $this->getRunningScans() ) > 0 || count( $opts->getScansToBuild() ) > 0;
	}

	public function getQueueBuilder() :Build\QueueBuilder {
		if ( empty( $this->oQueueBuilder ) ) {
			$this->oQueueBuilder = ( new Build\QueueBuilder( 'shield_scanqbuild' ) )
				->setMod( $this->getMod() );
		}
		return $this->oQueueBuilder;
	}

	public function getQueueProcessor() :QueueProcessor {
		if ( empty( $this->oQueueProcessor ) ) {
			$this->oQueueProcessor = ( new QueueProcessor( 'shield_scanq' ) )
				->setMod( $this->getMod() )
				->setExpirationInterval( MINUTE_IN_SECONDS*10 );
		}
		return $this->oQueueProcessor;
	}
}
