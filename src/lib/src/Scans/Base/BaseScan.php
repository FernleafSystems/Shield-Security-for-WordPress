<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseScan {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Common\ScanActionConsumer;

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
		$oQ = ( new ScanActionQuery() )->setScanActionVO( $oAction );
		if ( $oStore->isLocked() ) {
			if ( $oQ->isLockExpired() ) {
				$oStore->unlockAction();
			}
			else {
				throw new \Exception( 'Scan is currently locked.' );
			}
		}

		if ( $oQ->isScanExpired() ) {
			$oStore->deleteAction();
			throw new \Exception( 'Scan was expired and was forcefully deleted.' );
		}

		$oStore->lockAction();
	}

	protected function scan() {
		/** @var BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();

		if ( empty( $oAction->items ) ) {
			$oAction->finished_at = Services::Request()->ts();
		}
		else {
			$this->scanSlice();
			if ( empty( $oAction->items ) ) {
				$oAction->finished_at = Services::Request()->ts();
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

		if ( $oAction->finished_at > 0 ) {
			$oStore->deleteAction();
		}
		else {
			$oStore->storeAction();
		}

		$oStore->unlockAction();
	}
}