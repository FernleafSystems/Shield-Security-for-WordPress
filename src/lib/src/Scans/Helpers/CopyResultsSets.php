<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;

class CopyResultsSets {

	/**
	 * @param ResultsSet $oFromRS1
	 * @param ResultsSet $oToRS2
	 */
	public function copyTo( $oFromRS1, $oToRS2 ) {
		foreach ( $oFromRS1->getAllItems() as $oIt ) {
			$oToRS2->addItem( $oIt );
		}
	}
}