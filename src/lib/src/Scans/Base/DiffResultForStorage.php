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
	 * @param ResultsSet $oExistingRes - will be updated with all items to DB Update
	 * @param ResultsSet $oNewResults  - will be adjusted with all item to DB Insert
	 * @return ResultsSet - A results set of all out-of-date records that need to be deleted.
	 */
	public function diff( $oExistingRes, $oNewResults ) {

		$toDelete = new ResultsSet();
		$merger = new Scans\Base\BaseMergeItems();

		// 1 Remove items in EXISTING that are not in NEW
		foreach ( $oExistingRes->getAllItems() as $oExistItem ) {
			if ( !$oNewResults->getItemExists( $oExistItem->hash ) ) {
				$oExistingRes->removeItemByHash( $oExistItem->hash );
				$toDelete->addItem( $oExistItem );
			}
		}

		// 2 Merge NEW items into Existing items
		foreach ( $oNewResults->getAllItems() as $oNewItem ) {
			if ( $oExistingRes->getItemExists( $oNewItem->hash ) ) {
				$merger->mergeItemTo( $oExistingRes->getItemByHash( $oNewItem->hash ), $oNewItem );
				$oNewResults->removeItemByHash( $oNewItem->hash );
			}
		}

		return $toDelete;
	}
}