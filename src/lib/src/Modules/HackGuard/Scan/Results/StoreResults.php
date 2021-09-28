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

class StoreResults {

	use ModConsumer;

	public function store( QueueItemVO $scanItem, array $results ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !empty( $results ) ) {
			$dbhResItems = $mod->getDbH_ResultItems();
			/** @var ResultItemsDB\Ops\Select $resultSelector */
			$resultSelector = $dbhResItems->getQuerySelector();
			/** @var ScanResultsDB\Ops\Insert $scanResultsInserter */
			$scanResultsInserter = $mod->getDbH_ScanResults()->getQueryInserter();

			foreach ( $results as $result ) {
				/** @var ResultItemsDB\Ops\Record $resultItem */
				$resultItem = $resultSelector->filterByItemHash( $result[ 'hash' ] )->first();
				if ( empty( $resultItem ) ) {
					$record = $mod->getScanCon( $scanItem->scan )->buildScanResult( $result );
					$dbhResItems->getQueryInserter()->insert( $record );
					$resultItem = $resultSelector->filterByItemHash( $result[ 'hash' ] )->first();
				}
				$scanResultsInserter->setInsertData( [
					'scan_ref'       => $scanItem->scan_id,
					'resultitem_ref' => $resultItem->id,
				] )->query();
			}
		}

		$mod->getDbH_ScanItems()
			->getQueryUpdater()
			->updateById( $scanItem->qitem_id, [ 'finished_at' => Services::Request()->ts() ] );
	}
}
