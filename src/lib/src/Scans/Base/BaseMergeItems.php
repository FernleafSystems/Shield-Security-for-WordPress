<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class BaseMergeItems {

	/**
	 * Merges any data from $oMergeItem into $oBaseItem, overwriting Base Item data
	 * @param ResultItem $baseItem
	 * @param ResultItem $mergeItem
	 * @return ResultItem
	 */
	public function mergeItemTo( $baseItem, $mergeItem ) {
		foreach ( $mergeItem->getRawData() as $sKey => $mVal ) {
			$baseItem->{$sKey} = $mVal;
		}
		return $baseItem;
	}
}