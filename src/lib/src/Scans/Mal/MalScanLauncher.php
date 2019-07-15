<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class MalScanLauncher extends Shield\Utilities\AsyncActions\Launcher {

	use Shield\Modules\ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		if ( $this->isActionLocked() ) {
			throw new \Exception( 'Scan is currently locked.' );
		}
		$this->lockAction();

		/** @var Shield\Modules\HackGuard\Options $oOpts */
		$oOpts = $this->getMod()->getOptions();
		$oReq = Services::Request();

		$aDef = $this->readActionDefinitionFromDisk();
		if ( empty( $aDef ) ) {
			/** @var MalScanActionVO $oAction */
			$oAction = $this->getAction();
			$oAction->ts_start = $oReq->ts();
			$oAction->file_scan_limit = $oOpts->getFileScanLimit();
			$oAction->is_async = true;
			$oAction->paths_whitelisted = $oOpts->getMalwareWhitelistPaths();
			$oAction->patterns_regex = $oOpts->getMalSignaturesRegex();
			$oAction->patterns_simple = $oOpts->getMalSignaturesSimple();
			$oAction->files_map = ( new Shield\Scans\Mal\BuildFileMap() )
				->setWhitelistedPaths( $oAction->paths_whitelisted )
				->build();
		}
		else {
			$oAction = $this->getAction()
							->applyFromArray( $aDef );
		}

		if ( empty( $oAction->files_map ) ) {
			$oAction->ts_finish = $oReq->ts();
			$this->deleteAction();
		}
		else {
			$this->scanFileMapSlice();
			$this->storeAction();
		}

		$this->unlockAction();
	}

	/**
	 * @return $this
	 */
	private function scanFileMapSlice() {
		/** @var MalScanActionVO $oAction */
		$oAction = $this->getAction();

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

		{ // update file map to remove scanned files
			$oAction->files_map = array_slice( $oAction->files_map, $oAction->file_scan_limit );
		}

		return $this;
	}
}