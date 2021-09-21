<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class DiffResultForStorage
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class DiffResultForStorage {

	/**
	 * The Existing set will be updated to reflect the new current status of the scan
	 * @param ResultsSet $existingResults - will be updated with all items to DB Update
	 * @param ResultsSet $newResults      - will be adjusted with all item to DB Insert
	 * @return ResultsSet - A results set of all out-of-date records that need to be deleted.
	 */
	public function diff( $existingResults, $newResults ) {

		$toDelete = new ResultsSet();
		$merger = new Scans\Base\BaseMergeItems();

		// 1 Remove items in EXISTING that are not in NEW
		foreach ( $existingResults->getAllItems() as $oExistItem ) {
			if ( !$newResults->getItemExists( $oExistItem->hash ) ) {
				$existingResults->removeItemByHash( $oExistItem->hash );
				$toDelete->addItem( $oExistItem );
			}
		}

		// 2 Merge NEW items into Existing items
		foreach ( $newResults->getAllItems() as $oNewItem ) {
			if ( $existingResults->getItemExists( $oNewItem->hash ) ) {
				$merger->mergeItemTo( $existingResults->getItemByHash( $oNewItem->hash ), $oNewItem );
				$newResults->removeItemByHash( $oNewItem->hash );
			}
		}

		return $toDelete;
	}
}