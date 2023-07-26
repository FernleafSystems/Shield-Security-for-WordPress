<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\RecordConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller;
use FernleafSystems\Wordpress\Services\Services;

class PopulateScanItems {

	use Controller\ScanControllerConsumer;
	use RecordConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$scanCon = $this->getScanController();
		$dbhItems = $scanCon->mod()->getDbH_ScanItems();

		$scanRecord = $this->getRecord();
		$scanAction = $scanCon->buildScanAction();

		// ScanItems are stored separately
		$allItems = $scanAction->items;
		unset( $scanAction->items );

		$scanRecord->meta = $scanAction->getRawData();
		$scanCon->mod()
				->getDbH_Scans()
				->getQueryUpdater()
				->updateById( $scanRecord->id, [
					'meta' => $scanRecord->getRawData()[ 'meta' ]
				] );

		$sliceSize = $scanCon->getQueueGroupSize();

		/** @var ScanItemsDB\Ops\Record $newRecord */
		$newRecord = $dbhItems->getRecord();
		$newRecord->scan_ref = $scanRecord->id;
		do {
			$newRecord->items = \array_slice( $allItems, 0, $sliceSize );
			$dbhItems->getQueryInserter()->insert( $newRecord );
			$allItems = \array_slice( $allItems, $sliceSize );
		} while ( !empty( $allItems ) );

		// Marks the scan record as ready to run. It cannot run until this flag is set.
		// This prevents a timing issue where we're populating scan items but the scan could get picked up and executed.
		// TODO: review whether this entirely necessary depending on how scans are kicked off.
		$scanCon->mod()
				->getDbH_Scans()
				->getQueryUpdater()
				->updateRecord( $scanRecord, [
					'ready_at' => Services::Request()->ts()
				] );
	}
}
