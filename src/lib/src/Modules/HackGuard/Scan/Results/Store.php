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
			$resultItem = $resultSelector->filterByItemHash( $result[ 'hash' ] )->first();
			if ( empty( $resultItem ) ) {
				$dbhResItems->getQueryInserter()->insert( $record );
				$resultItem = $resultSelector->filterByItemHash( $result[ 'hash' ] )->first();
			}
			else {
				$record->meta = array_merge( $resultItem->meta, $record->meta );

				$updateData = [
					'item_deleted_at'   => 0,
					'item_repaired_at'  => 0,
					'attempt_repair_at' => 0,
					'meta'              => $record->getRawData()[ 'meta' ]
				];

				// TODO: do we need any dynamic adjustment depending on type of scan result?

				$dbhResItems->getQueryUpdater()
							->updateById( $resultItem->id, $updateData );
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
