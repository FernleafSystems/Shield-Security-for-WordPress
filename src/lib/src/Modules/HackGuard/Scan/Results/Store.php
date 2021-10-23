<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Scan\Queue\QueueItemVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ResultItems as ResultItemsDB,
	ResultItemMeta as ResultItemMetaDB,
	ScanResults as ScanResultsDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Store {

	use ModConsumer;

	public function store( QueueItemVO $queueItem, array $results ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$dbhResItems = $mod->getDbH_ResultItems();
		$dbhResItemMetas = $mod->getDbH_ResultItemMeta();
		/** @var ResultItemsDB\Ops\Select $resultSelector */
		$resultSelector = $dbhResItems->getQuerySelector();
		/** @var ScanResultsDB\Ops\Insert $scanResultsInserter */
		$scanResultsInserter = $mod->getDbH_ScanResults()->getQueryInserter();

		foreach ( $results as $result ) {

			$record = $mod->getScanCon( $queueItem->scan )->buildScanResult( $result );

			/** @var ResultItemsDB\Ops\Record $resultItem */
			$resultItem = $resultSelector->filterByItemID( $record->item_id )
										 ->filterByItemNotDeleted()
										 ->filterByItemNotRepaired()
										 ->first();
			if ( empty( $resultItem ) ) {
				$dbhResItems->getQueryInserter()->insert( $record );
				$resultItem = $resultSelector->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
			}
			else {
				/** @var ResultItemMetaDB\Ops\Delete $metaDeleter */
				$metaDeleter = $dbhResItemMetas->getQueryDeleter();
				$metaDeleter->filterByResultItemRef( $resultItem->id )->query();
			}

			foreach ( $record->meta as $metaKey => $metaValue ) {
				/** @var ResultItemMetaDB\Ops\Insert $metaInserter */
				$metaInserter = $dbhResItemMetas->getQueryInserter();
				$metaInserter->setInsertData( [
					'ri_ref'     => $resultItem->id,
					'meta_key'   => $metaKey,
					'meta_value' => is_scalar( $metaValue ) ? $metaValue : json_encode( $metaValue ),
				] )->query();
			}

			$scanResultsInserter->setInsertData( [
				'scan_ref'       => $queueItem->scan_id,
				'resultitem_ref' => $resultItem->id,
			] )->query();
		}
		$this->markQueueItemAsFinished( $queueItem );
	}

	private function markQueueItemAsFinished( QueueItemVO $queueItem ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_ScanItems()
			->getQueryUpdater()
			->updateById( $queueItem->qitem_id, [
				'finished_at' => Services::Request()->ts()
			] );
	}
}
