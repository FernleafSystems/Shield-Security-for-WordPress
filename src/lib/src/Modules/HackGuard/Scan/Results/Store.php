<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Scan\Queue\QueueItemVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\{
	ResultItems as ResultItemsDB,
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
		/** @var ResultItemsDB\Ops\Select $resultSelector */
		$resultSelector = $dbhResItems->getQuerySelector();
		/** @var ScanResultsDB\Ops\Insert $scanResultsInserter */
		$scanResultsInserter = $mod->getDbH_ScanResults()->getQueryInserter();

		foreach ( $results as $result ) {

			$record = $mod->getScanCon( $queueItem->scan )->buildScanResult( $result );

			/** @var ResultItemsDB\Ops\Record $resultItem */
			$resultItem = $resultSelector->filterByItemID( $record->item_id )
										 ->filterByItemHash( $record->hash )
										 ->filterByItemNotDeleted()
										 ->filterByItemNotRepaired()
										 ->first();
			if ( empty( $resultItem ) ) {
				$dbhResItems->getQueryInserter()->insert( $record );
				$resultItem = $resultSelector->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );
			}
			else {
				$record->meta = array_merge( $resultItem->meta, $record->meta );
				$dbhResItems->getQueryUpdater()
							->updateById( $resultItem->id, [
								'meta' => $record->getRawData()[ 'meta' ]
							] );
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
