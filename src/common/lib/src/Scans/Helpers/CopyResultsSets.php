<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;

/**
 * Class MergeResultsSets
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class CopyResultsSets {

	/**
	 * @param BaseResultsSet $oRS1
	 * @param BaseResultsSet $oRS2
	 */
	public function copyTo( $oRS1, $oRS2 ) {
		foreach ( $oRS1->getAllItems() as $oIt ) {
			$oRS2->addItem( $oIt );
		}
	}
}