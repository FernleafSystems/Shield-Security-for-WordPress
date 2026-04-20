<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ResultItemMeta\Ops as ResultItemMetaDB,
	ResultItems\Ops as ResultItemsDB,
	ScanResults\Ops as ScanResultsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueItemVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Store {

	use PluginControllerConsumer;

	public function store( QueueItemVO $queueItem, array $results ) {
		$dbCon = self::con()->db_con;

		$dbhResItemMetas = $dbCon->scan_result_item_meta;
		/** @var ResultItemsDB\Select $resultSelector */
		$resultSelector = $dbCon->scan_result_items->getQuerySelector();
		/** @var ScanResultsDB\Select $scanResultsSelector */
		$scanResultsSelector = $dbCon->scan_results->getQuerySelector();

		foreach ( $results as $result ) {

			$scanResult = self::con()->comps->scans->getScanCon( $queueItem->scan )->buildScanResult( $result );

			/** @var ?ResultItemsDB\Record $resultRecord */
			$resultRecord = $resultSelector->filterByScan( $queueItem->scan )
										   ->filterByItemType( $scanResult->item_type )
										   ->filterByItemID( $scanResult->item_id )
										   ->filterByUnresolved()
										   ->first();
			if ( empty( $resultRecord ) ) {
				$dbCon->scan_result_items->getQueryInserter()->insert( $scanResult );
				$resultRecord = $resultSelector->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
			}
			else {
				$dbCon->scan_result_items->getQueryUpdater()->updateRecord( $resultRecord, [
					'asset_type'        => $scanResult->asset_type,
					'asset_key'         => $scanResult->asset_key,
					'auto_filtered_at'  => $scanResult->auto_filtered_at,
					'last_seen_at'      => $scanResult->last_seen_at,
					'resolved_at'       => 0,
					'resolution_reason' => '',
				] );

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
					'meta_value' => \is_scalar( $metaValue ) ? $metaValue : \wp_json_encode( $metaValue ),
				] )->query();
			}

			if ( $scanResultsSelector->filterByScan( $queueItem->scan_id )
									 ->filterByResultItem( (int)$resultRecord->id )
									 ->count() === 0 ) {
				$dbCon->scan_results->getQueryInserter()
									->setInsertData( [
										'scan_ref'       => $queueItem->scan_id,
										'resultitem_ref' => $resultRecord->id,
									] )
									->query();
			}
		}
		$this->markQueueItemAsFinished( $queueItem );
	}

	private function markQueueItemAsFinished( QueueItemVO $queueItem ) {
		self::con()
			->db_con
			->scan_items
			->getQueryUpdater()
			->updateById( $queueItem->qitem_id, [
				'finished_at' => Services::Request()->ts()
			] );
	}
}
