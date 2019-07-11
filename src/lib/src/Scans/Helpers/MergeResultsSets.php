<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseResultsSet;

/**
 * Class MergeResultsSets
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class MergeResultsSets {

	/**
	 * @param BaseResultsSet $oRs1
	 * @param BaseResultsSet $oRs2
	 * @return BaseResultsSet
	 */
	public function merge( $oRs1, $oRs2 ) {
		$oNewRs = clone $oRs1;
		foreach ( $oRs2->getAllItems() as $oIt ) {
			$oNewRs->addItem( $oIt );
		}
		return $oNewRs;
	}
}