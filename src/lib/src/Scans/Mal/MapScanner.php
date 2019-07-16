<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class MapScanner extends Shield\Scans\Base\Files\BaseFileMapScan {

	use Shield\Modules\ModConsumer;

	/**
	 * @return ScanActionVO
	 * @throws \Exception
	 */
	protected function scan() {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		if ( !$oAction instanceof ScanActionVO ) {
			throw new \Exception( 'MalScan Action VO not provided.' );
		}

		$aDef = $this->readActionDefinitionFromDisk();
		if ( empty( $aDef ) ) {
			( new BuildScanAction )
				->setMod( $this->getMod() )
				->setScanActionVO( $oAction )
				->build();
			$this->storeAction();
		}
		else {
			$oAction = $this->getScanActionVO()
							->applyFromArray( $aDef );

			if ( empty( $oAction->files_map ) ) {
				$oAction->ts_finish = Services::Request()->ts();
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