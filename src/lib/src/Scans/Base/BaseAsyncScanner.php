<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files\FileScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseAsyncScanner extends BaseAsyncAction {

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function run() {
		$this->preScan();
		$this->scan();
		$this->postScan();
		return $this;
	}

	/**
	 * @throws \Exception
	 */
	protected function preScan() {
		if ( !Services::WpFs()->exists( $this->getTmpDir() ) ) {
			throw new \Exception( 'TMP Dir does not exist' );
		}
		if ( $this->isActionLocked() ) {
			throw new \Exception( 'Scan is currently locked.' );
		}

		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof ScanActionVO ) {
			throw new \Exception( 'Action VO not provided.' );
		}
		if ( empty( $oAction->id ) ) {
			throw new \Exception( 'Action ID not provided.' );
		}

		@ignore_user_abort( true );

		$this->lockAction();
	}

	/**
	 * @return ScanActionVO
	 */
	abstract protected function scan();

	protected function postScan() {
		$oAction = $this->getScanActionVO();
		if ( $oAction->ts_finish > 0 ) {
			$this->deleteAction();
		}
		else {
			$this->storeAction();
		}

		$this->unlockAction();
	}

	/**
	 * @return $this
	 */
	protected function scanFileMapSlice() {
		/** @var FileScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		$oTempRs = ( new ScanFromFileMap() )
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
		}
		else {
			$oAction->files_map = [];
		}

		return $this;
	}
}