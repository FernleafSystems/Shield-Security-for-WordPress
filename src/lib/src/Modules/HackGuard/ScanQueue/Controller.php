<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * Class Controller
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ScanQueue
 */
class Controller {

	use ModConsumer;

	/**
	 * @var QueueProcessor
	 */
	private $oQueue;

	public function __construct() {
		add_action( 'init', [ $this, 'onWpInit' ] );
	}

	public function onWpInit() {
		$this->getQueueProcessor();
	}

	/**
	 * @return bool[]
	 */
	public function getScansRunningStates() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Select $oSel */
		$oSel = $oMod->getDbHandler_ScanQueue()->getQuerySelector();

		$aScans = array_fill_keys( $oMod->getAllScanSlugs(), false );
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
			if ($nInitiated > 0) {
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
		return count( $this->getRunningScans() ) > 0;
	}

	/**
	 * @param string $sScanSlug
	 */
	public function startScan( $sScanSlug ) {
		try {
			( new ScanInitiate() )
				->setMod( $this->getMod() )
				->setQueueProcessor( $this->getQueueProcessor() )
				->init( $sScanSlug );
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @return QueueProcessor
	 */
	public function getQueueProcessor() {
		if ( empty( $this->oQueue ) ) {
			$this->oQueue = ( new QueueProcessor() )->setMod( $this->getMod() );
		}
		return $this->oQueue;
	}
}
