<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;

/**
 * Class MergeResultsSets
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class CopyResultsSets {

	/**
	 * @param BaseResultsSet $oFromRS1
	 * @param BaseResultsSet $oToRS2
	 */
	public function copyTo( $oFromRS1, $oToRS2 ) {
		foreach ( $oFromRS1->getAllItems() as $oIt ) {
			$oToRS2->addItem( $oIt );
		}
	}
}