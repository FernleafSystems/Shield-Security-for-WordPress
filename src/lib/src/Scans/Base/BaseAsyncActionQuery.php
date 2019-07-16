<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;

class BaseAsyncActionQuery extends Shield\Scans\Base\BaseAsyncAction {

	/**
	 * @return float
	 * @throws \Exception
	 */
	public function getPercentageComplete() {
		$aDef = $this->readActionDefinitionFromDisk();
		if ( empty( $aDef ) ) {
			throw new \Exception( 'Action file/data could not be retrieved' );
		}
		else {
			$oAction = $this->getScanActionVO()
							->applyFromArray( $aDef );

			if ( $oAction->ts_finish > 0 ) {
				$nPercent = 100;
			}
			else {
				$nPercent = $oAction->processed_items/$oAction->total_scan_items;
			}
		}

		return $nPercent;
	}
}