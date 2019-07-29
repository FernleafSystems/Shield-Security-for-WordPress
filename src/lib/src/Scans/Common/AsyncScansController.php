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
		$aScns = $this->getInitiatedScans();
		foreach ( $aScns as $sScanSlug => $aInfo ) {
			$nInitTs = $this->getScanInitTime( $sScanSlug );
			if ( $nInitTs > 0 && $nBoundary > $nInitTs ) {
				$this->removeInitiatedScan( $sScanSlug );
			}
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function abortAllScans() {
		return $this->setScans( [] );
	}

	/**
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 * @throws \Exception
	 */
	public function runScans() {
		if ( $this->isRunning() ) {
			throw new \Exception( 'Scans already running', 100 );
		}
		if ( !$this->hasScansToRun() ) {
			throw new \Exception( 'No scans to run', 101 );
		}

		$this->start();

		@ignore_user_abort( true );

		$aInitedScans = $this->getInitiatedScans();
		$aWorkingScan = array_shift( $aInitedScans );

		$oAction = $this->getScanAction( $aWorkingScan );
		$oAction->is_async = true;
		try {
			( new Shield\Scans\Common\ScanLauncher() )
				->setMod( $this->getMod() )
				->setScanActionVO( $oAction )
				->launch();
		}
		catch ( \Exception $oE ) {
			$this->end();
			throw new $oE();
		}

		// Remove scan from list so we know whether to fire another round
		if ( $oAction->ts_finish > 0 ) {
			$this->removeInitiatedScan( $oAction->id );
		}

		if ( $this->hasScansToRun() ) {
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

		$this->end();
		return $oAction;
	}

	/**
	 * @param array $aWorkingScan
	 * @return Shield\Scans\Base\BaseScanActionVO|mixed
	 */
	public function getScanAction( $aWorkingScan ) {
		$oAct = $this->getNewScanActionVO( $aWorkingScan[ 'id' ] );
		if ( $oAct instanceof Shield\Scans\Base\BaseScanActionVO ) {
			$oAct->applyFromArray( $aWorkingScan );

			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			$oAct->tmp_dir = $oMod->getScansTempDir();
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
	 * @param string $sScanSlug
	 * @return int
	 */
	public function getScanInitTime( $sScanSlug ) {
		return $this->getScanInfo( $sScanSlug )[ 'ts_init' ];
	}

	/**
	 * @param string $sScanSlug
	 * @return int
	 */
	private function getScanInfo( $sScanSlug ) {
		$aSns = $this->getInitiatedScans();
		$aScan = isset( $aSns[ $sScanSlug ] ) ? $aSns[ $sScanSlug ] : [];
		return array_merge(
			[
				'slug'    => '', // always set the slug
				'ts_init' => 0,
			],
			$aScan
		);
	}

	/**
	 * @return array[] - keys: scan slugs; values: array of ts_init, id
	 */
	public function getInitiatedScans() {
		$aSn = $this->getOpts()->getOpt( 'running_scans' );
		return is_array( $aSn ) ? $aSn : [];
	}

	/**
	 * @return bool
	 */
	public function hasScansToRun() {
		return count( $this->getInitiatedScans() ) > 0;
	}

	/**
	 * @param string $sScanSlug
	 * @return bool
	 */
	public function isScanInited( $sScanSlug ) {
		return $this->getScanInitTime( $sScanSlug ) > 0;
	}

	/**
	 * @param string $sScanSlug
	 * @return $this
	 */
	public function removeInitiatedScan( $sScanSlug ) {
		if ( $this->isScanInited( $sScanSlug ) ) {
			$aScans = $this->getInitiatedScans();
			unset( $aScans[ $sScanSlug ] );
			$this->setScans( $aScans );
		}
		return $this;
	}

	/**
	 * @param string $sScanSlug
	 * @return $this
	 */
	public function setScanInitiated( $sScanSlug ) {
		return $this->setScansInitiated( [ $sScanSlug ] );
	}

	/**
	 * @param string[] $aScanSlugs
	 * @return $this
	 */
	public function setScansInitiated( $aScanSlugs ) {
		$bUpdated = false;
		$aScans = $this->getInitiatedScans();
		foreach ( $aScanSlugs as $sScanSlug ) {
			if ( !$this->isScanInited( $sScanSlug ) ) {
				$bUpdated = true;
				$aScans[ $sScanSlug ] = [
					'id'      => $sScanSlug,
					'ts_init' => Services::Request()->ts(),
				];
			}
		}
		if ( $bUpdated ) {
			$this->setScans( $aScans );
		}
		return $this;
	}

	/**
	 * @param array $aScans
	 * @return $this
	 */
	private function setScans( $aScans ) {
		$this->getOpts()->setOpt( 'running_scans', $aScans );
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