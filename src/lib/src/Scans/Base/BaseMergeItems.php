<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseMergeItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
class BaseMergeItems {

	/**
	 * Merges any data from $oMergeItem into $oBaseItem, overwriting Base Item data
	 * @param ResultItem $oBaseItem
	 * @param ResultItem $oMergeItem
	 * @return ResultItem
	 */
	public function mergeItemTo( $oBaseItem, $oMergeItem ) {
		foreach ( $oMergeItem->getRawData() as $sKey => $mVal ) {
			$oBaseItem->{$sKey} = $mVal;
		}
		return $oBaseItem;
	}
}