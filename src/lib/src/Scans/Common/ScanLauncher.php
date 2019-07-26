<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Common;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ScanLauncher {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Base\ScanActionConsumer;

	/**
	 * Builds the Action Definition and handles the storage to and from disk.
	 * @return bool
	 * @throws \Exception
	 */
	public function launch() {
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof Base\BaseScanActionVO ) {
			throw new \Exception( 'Scan Action VO not provided.' );
		}

		@ignore_user_abort( true );

		$oStore = ( new Shield\Scans\Base\ActionStore() )
			->setScanActionVO( $oAction );

		$aDef = $oStore->readActionDefinitionFromDisk();

		// Only run a scan for async actions if the def was already on-disk
		$bRunScan = !$oAction->is_async || !empty( $aDef );

		if ( empty( $aDef ) ) {
			$this->getScanActionBuilder()
				 ->setMod( $this->getMod() )
				 ->setScanActionVO( $oAction )
				 ->build( !$oAction->is_async );
			if ( $oAction->is_async ) {
				$oStore->storeAction();
			}
		}
		else {
			$oAction->applyFromArray( $aDef );
			if ( !$oAction->is_items_built ) {
				try { // Build the scan items if it's not already done
					$this->getScanActionBuilder()
						 ->setMod( $this->getMod() )
						 ->setScanActionVO( $oAction )
						 ->buildScanItems();
					$oStore->storeAction();
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		if ( $bRunScan ) {
			$this->getScanner()
				 ->setScanActionVO( $oAction )
				 ->setMod( $this->getMod() )
				 ->run();
		}

		return true;
	}

	/**
	 * @return Base\BaseScan|mixed
	 */
	private function getScanner() {
		$sClass = $this->getScanActionVO()->getScanNamespace().'\\Scan';
		/** @var Base\BaseScan $o */
		$o = new $sClass();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $this->getScanActionVO() );
	}

	/**
	 * @return Base\BaseBuildScanAction|mixed
	 */
	private function getScanActionBuilder() {
		$sClass = $this->getScanActionVO()->getScanNamespace().'\\BuildScanAction';
		/** @var Base\BaseBuildScanAction $o */
		$o = new $sClass();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $this->getScanActionVO() );
	}
}