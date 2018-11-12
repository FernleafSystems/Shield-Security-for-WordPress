<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

/**
 * Class DiffResultForStorage
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\WpCore
 */
class DiffResultForStorage {

	/**
	 * The Existing set will be updated to reflect the new current status of the scan
	 * @param ResultsSet $oExistingRes
	 * @param ResultsSet $oNewRes
	 * @return ResultsSet - A results set of all out-of-date records that need to be deleted.
	 */
	public function diff( $oExistingRes, $oNewRes ) {

		$oToDelete = new ResultsSet();
		$oMerger = new Scans\Base\BaseMergeItems();

		// 1 Remove items in EXISTING that are not in NEW
		foreach ( $oExistingRes->getAllItems() as $oExistItem ) {
			if ( !$oNewRes->getItemExists( $oExistItem->hash ) ) {
				$oExistingRes->removeItem( $oExistItem->hash );
				$oToDelete->addItem( $oExistItem );
			}
		}

		// 2 Add items to EXISTING that are only in NEW
		foreach ( $oNewRes->getAllItems() as $oNew ) {
			if ( !$oExistingRes->getItemExists( $oNew->hash ) ) {
				$oExistingRes->addItem( $oNew );
				$oNewRes->removeItem( $oNew->hash );
			}
		}

		// 3 Merge NEW items into Existing
		foreach ( $oNewRes->getAllItems() as $oNew ) {
			$oMerger->mergeItemTo( $oExistingRes->getItemByHash( $oNew->hash ), $oNew );
			$oNewRes->removeItem( $oNew->hash );
		}

		return $oExistingRes;
	}
}