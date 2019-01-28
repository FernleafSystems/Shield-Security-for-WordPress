<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class BaseMergeItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Base
 */
class BaseMergeItems {

	/**
	 * Merges any data from $oMergeItem into $oBaseItem, overwriting Base Item data
	 * @param BaseResultItem $oBaseItem
	 * @param BaseResultItem $oMergeItem
	 * @return BaseResultItem
	 */
	public function mergeItemTo( $oBaseItem, $oMergeItem ) {
		foreach ( $oMergeItem->getRawDataAsArray() as $sKey => $mVal ) {
			$oBaseItem->{$sKey} = $mVal;
		}
		return $oBaseItem;
	}
}