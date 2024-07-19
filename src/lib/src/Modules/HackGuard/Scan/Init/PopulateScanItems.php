<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\RecordConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PopulateScanItems {

	use PluginControllerConsumer;
	use RecordConsumer;
	use ScanControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() {
		$scanCon = $this->getScanController();
		$dbhItems = self::con()->db_con->scan_items;

		$scanRecord = $this->getRecord();
		$scanAction = $scanCon->buildScanAction();

		// ScanItems are stored separately
		$allItems = $scanAction->items;
		unset( $scanAction->items );

		$scanRecord->meta = $scanAction->getRawData();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanRecord->id, [
			'meta' => $scanRecord->getRawData()[ 'meta' ]
		] );

		$sliceSize = $scanCon->getQueueGroupSize();

		/** @var ScanItemsDB\Record $newRecord */
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
		self::con()->db_con->scans->getQueryUpdater()->updateRecord( $scanRecord, [
			'ready_at' => Services::Request()->ts()
		] );
	}
}
