<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModConsumer,
	Scan\Queue\QueueItemVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ResultItemMeta\Ops as ResultItemMetaDB,
	ResultItems\Ops as ResultItemsDB,
};
use FernleafSystems\Wordpress\Services\Services;

class Store {

	use ModConsumer;

	public function store( QueueItemVO $queueItem, array $results ) {
		$dbCon = self::con()->db_con;

		$dbhResItems = $dbCon->dbhResultItems();
		$dbhResItemMetas = $dbCon->dbhResultItemMeta();
		/** @var ResultItemsDB\Select $resultSelector */
		$resultSelector = $dbhResItems->getQuerySelector();

		foreach ( $results as $result ) {

			$scanResult = $this->mod()
							   ->getScansCon()
							   ->getScanCon( $queueItem->scan )
							   ->buildScanResult( $result );

			/** @var ResultItemsDB\Record $resultRecord */
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
				/** @var ResultItemMetaDB\Delete $metaDeleter */
				$metaDeleter = $dbhResItemMetas->getQueryDeleter();
				$metaDeleter->filterByResultItemRef( $resultRecord->id )->query();
			}

			foreach ( $scanResult->meta as $metaKey => $metaValue ) {
				/** @var ResultItemMetaDB\Insert $metaInserter */
				$metaInserter = $dbhResItemMetas->getQueryInserter();
				$metaInserter->setInsertData( [
					'ri_ref'     => $resultRecord->id,
					'meta_key'   => $metaKey,
					'meta_value' => \is_scalar( $metaValue ) ? $metaValue : \json_encode( $metaValue ),
				] )->query();
			}

			$dbCon->dbhScanResults()
				  ->getQueryInserter()
				  ->setInsertData( [
					  'scan_ref'       => $queueItem->scan_id,
					  'resultitem_ref' => $resultRecord->id,
				  ] )
				  ->query();
		}
		$this->markQueueItemAsFinished( $queueItem );
	}

	private function markQueueItemAsFinished( QueueItemVO $queueItem ) {
		self::con()
			->db_con
			->dbhScanItems()
			->getQueryUpdater()
			->updateById( $queueItem->qitem_id, [
				'finished_at' => Services::Request()->ts()
			] );
	}
}
