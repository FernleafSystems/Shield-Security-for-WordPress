<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScansController
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Common
 */
class AsyncScansController {

	use Shield\Modules\ModConsumer;

	/**
	 * @var bool
	 */
	private $bIsRunning;

	/**
	 * @return $this
	 */
	public function cleanStaleScans() {
		$nBoundary = Services::Request()->ts() - 600;

		$oJob = $this->loadScansJob();
		foreach ( $oJob->getInitiatedScans() as $sScanSlug => $aInfo ) {
			$nInitTs = $oJob->getScanInitTime( $sScanSlug );
			if ( $nInitTs > 0 && $nBoundary > $nInitTs ) {
				$oJob->removeInitiatedScan( $sScanSlug );
			}
		}

		return $this->storeScansJob( $oJob );
	}

	/**
	 * @return $this
	 */
	public function abortAllScans() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		Services::WpFs()->deleteDir( $oMod->getScansTempDir() );
		$oJob = $this->loadScansJob();
		$oJob->clearScans();
		return $this->storeScansJob( $oJob );
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function runScans() {
		if ( $this->isRunning() ) {
			throw new \Exception( 'Scans already running', 100 );
		}
		if ( !$this->loadScansJob()->hasScansToRun() ) {
			throw new \Exception( 'No scans to run', 101 );
		}

		$this->start();

		@ignore_user_abort( true );

		$oAction = $this->getWorkingScanAction();
		try {
			( new Shield\Scans\Common\ScanLauncher() )
				->setMod( $this->getMod() )
				->setScanActionVO( $oAction )
				->launch();
		}
		catch ( \Exception $oE ) {
			$this->end();
			throw $oE;
		}

		// Mark scan as finished so we know whether to fire another round
		if ( $oAction->ts_finish > 0 ) {
			$this->setScanAsFinished( $oAction );
		}

		if ( $this->loadScansJob()->hasScansToRun() ) {
			Services::HttpRequest()
					->get(
						add_query_arg(
							[
								'shield_action' => 'scan_async_process',
								'scan_key'      => $this->getOpts()->getScanKey()
							],
							Services::WpGeneral()->getHomeUrl()
						),
						[
							'blocking' => true,
							'timeout'  => 5,
						]
					);
		}
		else {
			$this->abortAllScans();
		}

		$this->end();
		return $oAction;
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO
	 */
	private function getWorkingScanAction() {
		$oJob = $this->loadScansJob();

		$aWorkingScan = $oJob->getCurrentScan();
		if ( empty( $aWorkingScan ) ) {
			$aUnfinished = $oJob->getUnfinishedScans();
			$aWorkingScan = array_shift( $aUnfinished );
			$oJob->setScanAsCurrent( $aWorkingScan[ 'id' ] );
			$this->storeScansJob( $oJob );
		}

		$oAction = $this->getScanAction( $aWorkingScan );

		return $oAction;
	}

	/**
	 * @param Shield\Scans\Base\BaseScanActionVO $oAction
	 * @return $this
	 */
	protected function setScanAsFinished( $oAction ) {
		$oJob = $this->loadScansJob();
		$aWorkingScan = $oJob->getScanInfo( $oAction->id );
		$aWorkingScan[ 'ts_finish' ] = $oAction->ts_finish;
		$oJob->setScanInfo( $oAction->id, $aWorkingScan );
		$oJob->setScanAsCurrent( $oAction->id, false );
		$this->storeScansJob( $oJob );
		return $this;
	}

	/**
	 * @param array $aWorkingScan
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	private function getScanAction( $aWorkingScan ) {
		$oAct = $this->getNewScanActionVO( $aWorkingScan[ 'id' ] );
		if ( $oAct instanceof Shield\Scans\Base\BaseScanActionVO ) {
			$oAct->applyFromArray( $aWorkingScan );

			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			$oAct->tmp_dir = $oMod->getScansTempDir();

			/**
			 * Only update is_cron if this is true so we don't overwrite it
			 * later with false on an async-request
			 */
			if ( $this->loadScansJob()->is_cron ) {
				$oAct->is_cron = true;
			}

			$oAct->is_async = true;
		}

		return $oAct;
	}

	/**
	 * @param $sScanSlug
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	private function getNewScanActionVO( $sScanSlug ) {
		$oVO = null;
		switch ( $sScanSlug ) {
			case 'apc':
				$oVO = new Shield\Scans\Apc\ScanActionVO();
				break;
			case 'mal':
				$oVO = new Shield\Scans\Mal\ScanActionVO();
				break;
			case 'ptg':
				$oVO = new Shield\Scans\Ptg\ScanActionVO();
				break;
			case 'ufc':
				$oVO = new Shield\Scans\Ufc\ScanActionVO();
				break;
			case 'wcf':
				$oVO = new Shield\Scans\Wcf\ScanActionVO();
				break;
			case 'wpv':
				$oVO = new Shield\Scans\Wpv\ScanActionVO();
				break;
		}
		return $oVO;
	}

	/**
	 * @param bool $bNew
	 * @return ScansJobVO
	 */
	public function loadScansJob( $bNew = false ) {
		$aSn = $this->getOpts()->getOpt( 'scans_job' );
		if ( $bNew || !is_array( $aSn ) ) {
			$aSn = [];
		}
		return ( new ScansJobVO() )->applyFromArray( $aSn );
	}

	/**
	 * @param string[] $aScanSlugs
	 * @return $this
	 */
	public function setupNewScanJob( $aScanSlugs ) {
		$oJob = $this->loadScansJob( true );
		$oJob->is_cron = Services::WpGeneral()->isCron();

		foreach ( $aScanSlugs as $sScanSlug ) {
			$oJob->setScanInfo( $sScanSlug, [
				'id'      => $sScanSlug,
				'ts_init' => Services::Request()->ts(),
			] );
		}

		return $this->storeScansJob( $oJob );
	}

	/**
	 * @param ScansJobVO $oJob
	 * @return $this
	 */
	private function storeScansJob( $oJob ) {
		$this->getOpts()->setOpt( 'scans_job', $oJob->getRawDataAsArray() );
		$this->getMod()->savePluginOptions();
		return $this;
	}

	/**
	 * @return Shield\Modules\HackGuard\Options
	 */
	private function getOpts() {
		return $this->getMod()->getOptions();
	}

	/**
	 * @return bool
	 */
	private function start() {
		return $this->bIsRunning = true;
	}

	/**
	 * @return bool
	 */
	private function end() {
		return $this->bIsRunning = false;
	}

	/**
	 * @return bool
	 */
	private function isRunning() {
		return (bool)$this->bIsRunning;
	}
}