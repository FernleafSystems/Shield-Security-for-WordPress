<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\RecordConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
		$scanActionVO = $scanCon->newScanActionVO();
		$scanActionVO->scope_type = (string)( $scanRecord->scope_type ?? 'full' );
		$scanActionVO->scope_key = (string)( $scanRecord->scope_key ?? '' );
		$scanAction = $scanCon->buildScanAction( $scanActionVO );

		// ScanItems are stored separately
		$allItems = $scanAction->items;
		unset( $scanAction->items );

		$scanRecord->meta = $scanAction->getRawData();
		self::con()->db_con->scans->getQueryUpdater()->updateById( $scanRecord->id, [
			'meta' => $scanRecord->getRawData()[ 'meta' ],
		] );
		( new RunState() )->touch( (int)$scanRecord->id );

		if ( empty( $allItems ) ) {
			( new SetScanCompleted() )->run( (int)$scanRecord->id );
			return;
		}

		$sliceSize = $scanCon->getQueueGroupSize();

		/** @var ScanItemsDB\Record $newRecord */
		$newRecord = $dbhItems->getRecord();
		$newRecord->scan_ref = $scanRecord->id;
		do {
			$newRecord->items = \array_slice( $allItems, 0, $sliceSize );
			if ( !$dbhItems->getQueryInserter()->insert( $newRecord ) ) {
				throw new \RuntimeException( \sprintf( 'Failed to persist queue items for scan "%s".', $scanRecord->scan ) );
			}
			$allItems = \array_slice( $allItems, $sliceSize );
		} while ( !empty( $allItems ) );

		( new RunState() )->markBuilt( (int)$scanRecord->id );
	}
}
