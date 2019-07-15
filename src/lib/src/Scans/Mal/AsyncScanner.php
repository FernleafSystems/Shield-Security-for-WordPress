<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AsyncScanner extends Shield\Scans\Base\BaseAsyncScanner {

	use Shield\Modules\ModConsumer;

	/**
	 * @return MalScanActionVO
	 * @throws \Exception
	 */
	protected function scan() {
		/** @var MalScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof MalScanActionVO ) {
			throw new \Exception( 'MalScan Action VO not provided.' );
		}

		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$oReq = Services::Request();

		$aDef = $this->readActionDefinitionFromDisk();
		if ( empty( $aDef ) ) {
			$oAction->ts_start = $oReq->ts();
			$oAction->file_scan_limit = $oOpts->getFileScanLimit();
			$oAction->is_async = true;
			$oAction->paths_whitelisted = $oOpts->getMalwareWhitelistPaths();
			$oAction->patterns_regex = $oOpts->getMalSignaturesRegex();
			$oAction->patterns_simple = $oOpts->getMalSignaturesSimple();
			$oAction->files_map = ( new Shield\Scans\Mal\BuildFileMap() )
				->setWhitelistedPaths( $oAction->paths_whitelisted )
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
	 * @return $this
	 */
	private function scanFileMapSlice() {
		/** @var MalScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = ( new ScannerFromFileMap() )
			->setScanActionVO( $oAction )
			->run();

		if ( $oTempRs->hasItems() ) {
			$aNewItems = [];
			foreach ( $oTempRs->getAllItems() as $oItem ) {
				$aNewItems[] = $oItem->getRawDataAsArray();
			}
			if ( empty( $oAction->results ) ) {
				$oAction->results = [];
			}
			$oAction->results = array_merge( $oAction->results, $aNewItems );
		}

		if ( $oAction->file_scan_limit > 0 ) {
			$oAction->files_map = array_slice( $oAction->files_map, $oAction->file_scan_limit );
			$oAction->processed_items += $oAction->file_scan_limit;
		}
		else {
			$oAction->processed_items = count( $oAction->files_map );
			$oAction->files_map = [];
		}

		return $this;
	}
}