<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseScan {

	use Shield\Modules\ModConsumer,
		ScanActionConsumer;

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
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof BaseScanActionVO ) {
			throw new \Exception( 'Action VO not provided.' );
		}
		if ( empty( $oAction->id ) ) {
			throw new \Exception( 'Action ID not provided.' );
		}
		if ( !Services::WpFs()->exists( $oAction->tmp_dir ) ) {
			throw new \Exception( 'TMP Dir does not exist' );
		}

		$oStore = ( new ActionStore() )->setScanActionVO( $oAction );
		if ( $oStore->isActionLocked() ) {
			throw new \Exception( 'Scan is currently locked.' );
		}

		$oStore->lockAction();
	}

	protected function scan() {
		/** @var BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->scan_items ) ) {
			$oAction->ts_finish = Services::Request()->ts();
		}
		else {
			$this->scanSlice();
			if ( empty( $oAction->scan_items ) ) {
				$oAction->ts_finish = Services::Request()->ts();
			}
		}

		return $oAction;
	}

	/**
	 * @return void
	 */
	abstract protected function scanSlice();

	protected function postScan() {
		$oAction = $this->getScanActionVO();
		$oStore = ( new ActionStore() )->setScanActionVO( $oAction );

		if ( $oAction->ts_finish > 0 ) {
			$oStore->deleteAction();
		}
		else {
			$oStore->storeAction();
		}

		$oStore->unlockAction();
	}
}