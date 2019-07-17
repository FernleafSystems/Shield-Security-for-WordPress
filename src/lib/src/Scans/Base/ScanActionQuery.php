<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

class ScanActionQuery {

	use ScanActionConsumer;

	/**
	 * @return bool
	 */
	public function isRunning() {
		$sFile = ( new Shield\Scans\Base\ActionStore() )
			->setScanActionVO( $this->getScanActionVO() )
			->getActionFilePath();
		return (bool)Services::WpFs()->exists( $sFile );
	}

	/**
	 * @return float|null
	 * @throws \Exception
	 */
	public function getPercentageComplete() {
		$nPercent = null;
		if ( $this->isRunning() ) {
			$aDef = ( new Shield\Scans\Base\ActionStore() )
				->setScanActionVO( $this->getScanActionVO() )
				->readActionDefinitionFromDisk();
			if ( !empty( $aDef ) ) {
				$oAction = $this->getScanActionVO()
								->applyFromArray( $aDef );

				if ( $oAction->ts_finish > 0 ) {
					$nPercent = 100;
				}
				else {
					$nPercent = $oAction->processed_items/$oAction->total_scan_items;
				}
			}
		}

		return $nPercent;
	}
}