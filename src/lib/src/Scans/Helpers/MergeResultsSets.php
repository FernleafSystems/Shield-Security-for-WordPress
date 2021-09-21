<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet;

/**
 * Class MergeResultsSets
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class MergeResultsSets {

	/**
	 * @param ResultsSet $oRs1
	 * @param ResultsSet $oRs2
	 * @return ResultsSet
	 */
	public function merge( $oRs1, $oRs2 ) {
		$oNewRs = clone $oRs1;
		foreach ( $oRs2->getAllItems() as $oIt ) {
			$oNewRs->addItem( $oIt );
		}
		return $oNewRs;
	}
}