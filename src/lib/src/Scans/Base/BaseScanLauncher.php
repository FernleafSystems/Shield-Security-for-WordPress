<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class BaseScanLauncher {

	use Shield\Modules\ModConsumer,
		Shield\Scans\Base\ScanActionConsumer;

	/**
	 * Builds the Action Definition and handles the storage to and from disk.
	 * @return bool
	 * @throws \Exception
	 */
	public function launch() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof ScanActionVO ) {
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
				 ->build();
			if ( $oAction->is_async ) {
				$oStore->storeAction();
			}
		}
		else {
			$oAction->applyFromArray( $aDef );
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
	 * @return bool
	 */
	public function isRunning() {
		$sFile = ( new Shield\Scans\Base\ActionStore() )
			->setScanActionVO( $this->getScanActionVO() )
			->getActionFilePath();
		return Services::WpFs()->exists( $sFile );
	}

	/**
	 * @return BaseScan|mixed
	 */
	private function getScanner() {
		$sClass = $this->getNamespace().'\\Scan';
		/** @var BaseScan $o */
		$o = new $sClass();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $this->getScanActionVO() );
	}

	/**
	 * @return BaseBuildScanAction|mixed
	 */
	private function getScanActionBuilder() {
		$sClass = $this->getNamespace().'\\BuildScanAction';
		/** @var BaseBuildScanAction $o */
		$o = new $sClass();
		return $o->setMod( $this->getMod() )
				 ->setScanActionVO( $this->getScanActionVO() );
	}

	protected function loadScanActionBuilder() {
		return new BaseBuildScanAction();
	}

	/**
	 * @return string
	 */
	private function getNamespace() {
		try {
			$sName = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $oE ) {
			$sName = __NAMESPACE__;
		}
		return $sName;
	}
}