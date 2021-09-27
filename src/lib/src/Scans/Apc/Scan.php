<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield;

class Scan extends Shield\Scans\Base\BaseScan {

	protected function scanSlice() {
		/** @var ScanActionVO $action */
		$action = $this->getScanActionVO();

		$tempResultSet = $this->getScanController()->getNewResultsSet();

		foreach ( $action->items as $scanItem ) {
			$resultItem = $this->getItemScanner()->scan( $scanItem );
			if ( $resultItem instanceof Shield\Scans\Base\ResultItem ) {
				$tempResultSet->addItem( $resultItem );
			}
		}

		$newItems = [];
		if ( $tempResultSet->hasItems() ) {
			foreach ( $tempResultSet->getAllItems() as $resultItem ) {
				$newItems[] = $resultItem->getRawData();
			}
		}
		$action->results = $newItems;
	}

	protected function getItemScanner() :PluginScanner {
		return ( new PluginScanner() )
			->setScanController( $this->getScanController() )
			->setScanActionVO( $this->getScanActionVO() );
	}
}