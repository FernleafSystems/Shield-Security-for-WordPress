<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class DiffResultForStorage
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class DiffResultForStorage {

	/**
	 * The Existing set will be updated to reflect the new current status of the scan
	 * @param ResultsSet $oExistingRes - will be updated with all items to DB Update
	 * @param ResultsSet $oNewResults - will be adjusted with all item to DB Insert
	 * @return ResultsSet - A results set of all out-of-date records that need to be deleted.
	 */
	public function diff( $oExistingRes, $oNewResults ) {

		$oToDelete = new ResultsSet();
		$oMerger = new Scans\Base\BaseMergeItems();

		// 1 Remove items in EXISTING that are not in NEW
		foreach ( $oExistingRes->getAllItems() as $oExistItem ) {
			if ( !$oNewResults->getItemExists( $oExistItem->hash ) ) {
				$oExistingRes->removeItem( $oExistItem->hash );
				$oToDelete->addItem( $oExistItem );
			}
		}

		// 2 Merge NEW items into Existing items
		foreach ( $oNewResults->getAllItems() as $oNewItem ) {
			if ( $oExistingRes->getItemExists( $oNewItem->hash ) ) {
				$oMerger->mergeItemTo( $oExistingRes->getItemByHash( $oNewItem->hash ), $oNewItem );
				$oNewResults->removeItem( $oNewItem->hash );
			}
		}

		return $oToDelete;
	}
}