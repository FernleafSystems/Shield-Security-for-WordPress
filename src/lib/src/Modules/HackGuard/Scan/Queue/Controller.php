<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var HackGuard\Options $oOpts */
		$oOpts = $oMod->getOptions();
		/** @var Select $oSel */
		$oSel = $oMod->getDbHandler_ScanQueue()->getQuerySelector();

		$aScans = array_fill_keys( $oOpts->getScanSlugs(), false );
		foreach ( $oSel->getInitiatedScans() as $sInitScan ) {
			$aScans[ $sInitScan ] = true;
		}
		return $aScans;
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oDbH = $oMod->getDbHandler_ScanQueue();
		/** @var Select $oSel */
		$oSel = $oDbH->getQuerySelector();

		$aUnfinished = $oSel->getUnfinishedScans();
		$nProgress = 1;
		if ( $oSel->getUnfinishedScans() > 0 ) {
			$nInitiated = count( $oSel->getInitiatedScans() );
			if ( $nInitiated > 0 ) {
				$nProgress = 1 - ( count( $aUnfinished )/count( $oSel->getInitiatedScans() ) );
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
		$oOpts = $this->getMod()->getOptions();
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
			$oOpts = $this->getMod()->getOptions();
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
			$this->oQueueProcessor = ( new QueueProcessor( 'shield_scanq' ) )
				->setMod( $this->getMod() )
				->setExpirationInterval( MINUTE_IN_SECONDS*10 );
		}
		return $this->oQueueProcessor;
	}
}
