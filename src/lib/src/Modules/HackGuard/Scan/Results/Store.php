<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModConsumer,
	Scan\Queue\QueueItemVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ResultItemMeta as ResultItemMetaDB,
	ResultItems as ResultItemsDB,
	ScanResults as ScanResultsDB
};
use FernleafSystems\Wordpress\Services\Services;

class Store {

	use ModConsumer;

	public function store( QueueItemVO $queueItem, array $results ) {
		$mod = $this->mod();

		$dbhResItems = $mod->getDbH_ResultItems();
		$dbhResItemMetas = $mod->getDbH_ResultItemMeta();
		/** @var ResultItemsDB\Ops\Select $resultSelector */
		$resultSelector = $dbhResItems->getQuerySelector();
		/** @var ScanResultsDB\Ops\Insert $scanResultsInserter */
		$scanResultsInserter = $mod->getDbH_ScanResults()->getQueryInserter();

		foreach ( $results as $result ) {

			$scanResult = $mod->getScansCon()->getScanCon( $queueItem->scan )->buildScanResult( $result );

			/** @var ResultItemsDB\Ops\Record $resultRecord */
			$resultRecord = $resultSelector->filterByItemID( $scanResult->item_id )
										   ->filterByItemNotDeleted()
										   ->filterByItemNotRepaired()
										   ->first();
			if ( empty( $resultRecord ) ) {
				$dbhResItems->getQueryInserter()->insert( $scanResult );
				$resultRecord = $resultSelector->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
			}
			else {
				// If a result item is now auto-filtered, where before it wasn't, update it.
				if ( empty( $resultRecord->auto_filtered_at ) && $scanResult->auto_filtered_at > 0 ) {
					$dbhResItems->getQueryUpdater()->updateRecord( $resultRecord, [
						'auto_filtered_at' => $scanResult->auto_filtered_at
					] );
				}

				// Delete/Reset all metadata for the results in preparation for update.
				/** @var ResultItemMetaDB\Ops\Delete $metaDeleter */
				$metaDeleter = $dbhResItemMetas->getQueryDeleter();
				$metaDeleter->filterByResultItemRef( $resultRecord->id )->query();
			}

			foreach ( $scanResult->meta as $metaKey => $metaValue ) {
				/** @var ResultItemMetaDB\Ops\Insert $metaInserter */
				$metaInserter = $dbhResItemMetas->getQueryInserter();
				$metaInserter->setInsertData( [
					'ri_ref'     => $resultRecord->id,
					'meta_key'   => $metaKey,
					'meta_value' => is_scalar( $metaValue ) ? $metaValue : json_encode( $metaValue ),
				] )->query();
			}

			$scanResultsInserter->setInsertData( [
				'scan_ref'       => $queueItem->scan_id,
				'resultitem_ref' => $resultRecord->id,
			] )->query();
		}
		$this->markQueueItemAsFinished( $queueItem );
	}

	private function markQueueItemAsFinished( QueueItemVO $queueItem ) {
		$this->mod()
			 ->getDbH_ScanItems()
			 ->getQueryUpdater()
			 ->updateById( $queueItem->qitem_id, [
				 'finished_at' => Services::Request()->ts()
			 ] );
	}
}
