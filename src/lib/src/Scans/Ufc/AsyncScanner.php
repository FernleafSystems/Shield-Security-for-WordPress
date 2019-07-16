<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AsyncScanner extends Shield\Scans\Base\Files\BaseFileAsyncScanner {

	use Shield\Modules\ModConsumer;

	/**
	 * @return ScanActionVO
	 * @throws \Exception
	 */
	protected function scan() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof ScanActionVO ) {
			throw new \Exception( 'Scan Action VO not provided.' );
		}

		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$oReq = Services::Request();

		$aDef = $this->readActionDefinitionFromDisk();
		if ( empty( $aDef ) ) {
			$oAction->ts_start = $oReq->ts();
			$oAction->file_scan_limit = $oOpts->getFileScanLimit();
			$oAction->is_async = true;
			$oAction->exclusions = $oOpts->getUfcFileExclusions();
			$oAction->scan_dirs = $oOpts->getUfcScanDirectories();
			$oAction->files_map = ( new Shield\Scans\Ufc\BuildFileMap() )
				->setScanActionVO( $oAction )
				->build();
			$oAction->processed_items = 0;
			$oAction->total_scan_items = count( $oAction->files_map );
			$this->storeAction();
		}
		else {
			$oAction = $this->getScanActionVO()
							->applyFromArray( $aDef );

			if ( empty( $oAction->files_map ) ) {
				$oAction->ts_finish = $oReq->ts();
			}
			else {
				$this->scanFileMapSlice();
			}
		}

		return $oAction;
	}

	/**
	 * @return ScanFromFileMap
	 */
	protected function getScanFromFileMap() {
		return ( new ScanFromFileMap() )->setScanActionVO( $this->getScanActionVO() );
	}
}