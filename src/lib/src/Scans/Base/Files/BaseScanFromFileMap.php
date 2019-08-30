<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Files;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class BaseScanFromFileMap
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
abstract class BaseScanFromFileMap {

	use Scans\Common\ScanActionConsumer;

	/**
	 * @return Scans\Base\BaseResultsSet
	 */
	public function run() {
		/** @var Scans\Base\BaseScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		$oResultSet = $oAction->getNewResultsSet();

		if ( !empty( $oAction->items ) ) {

			if ( (int)$oAction->item_processing_limit > 0 ) {
				$aSlice = array_slice( $oAction->items, 0, $oAction->item_processing_limit );
			}
			else {
				$aSlice = $oAction->items;
			}

			$oAction->processed_items += count( $aSlice );

			foreach ( $aSlice as $nKey => $sFullPath ) {
				$oItem = $this->getFileScanner()->scan( $sFullPath );
				if ( $oItem instanceof Scans\Base\BaseResultItem ) {
					$oResultSet->addItem( $oItem );
				}
			}
		}

		return $oResultSet;
	}

	/**
	 * @return BaseFileScanner
	 */
	abstract protected function getFileScanner();
}