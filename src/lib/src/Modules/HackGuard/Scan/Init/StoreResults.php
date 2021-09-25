<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanResults as ScanResultsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class StoreResults {

	use ModConsumer;

	public function store( ScanQueueItemVO $scanItem, array $results ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !empty( $results ) ) {
			$dbh = $mod->getDbH_ScanResults();
			foreach ( $results as $result ) {
				/** @var ScanResultsDB\Ops\Record $record */
				$record = $mod->getScanCon( $scanItem->scan )->buildScanResult( $result );
				$record->scan_ref = $scanItem->scan_id;
				$dbh->getQueryInserter()->insert( $record );
			}
		}

		$mod->getDbH_ScanItems()
			->getQueryUpdater()
			->updateById( $scanItem->qitem_id, [ 'finished_at' => Services::Request()->ts() ] );
	}
}
