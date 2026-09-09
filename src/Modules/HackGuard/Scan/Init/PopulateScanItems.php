<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\RecordConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	ScanItems\Ops as ScanItemsDB,
	Scans\Ops as ScansDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\QueueHeartbeat;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO as AfsScanActionVO;

class PopulateScanItems {

	use PluginControllerConsumer;
	use RecordConsumer;
	use ScanControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :void {
		$scanCon = $this->getScanController();
		$dbhItems = self::con()->db_con->scan_items;

		$scanRecord = $this->getRecord();
		$scanActionVO = $scanCon->newScanActionVO();
		$scanActionVO->scope_type = (string)( $scanRecord->scope_type ?? 'full' );
		$scanActionVO->scope_key = (string)( $scanRecord->scope_key ?? '' );
		$heartbeat = new QueueHeartbeat();
		$scanID = $scanRecord->id;
		$scanActionVO->progress_callback = static function () use ( $heartbeat, $scanID ) :void {
			$heartbeat->tickBuilding( $scanID );
		};
		if ( $scanRecord->scan === 'afs' && $scanActionVO->scope_type === 'full' ) {
			if ( !$scanActionVO instanceof AfsScanActionVO ) {
				throw new \UnexpectedValueException( 'The AFS scan controller returned an invalid action.' );
			}
			$assets = ( new FindAssetsToSnap() )->run();
			$scanActionVO->asset_snapshot_eligibility = self::con()
				->comps
				->asset_coordinator
				->prepareFullScanSnapshotEligibility(
					$assets,
					static function () use ( $scanActionVO ) :void {
						$scanActionVO->tickProgress();
					}
				);
			if ( !$scanActionVO->hasValidAssetSnapshotEligibility() ) {
				throw new \UnexpectedValueException( 'Full-scan snapshot eligibility is invalid.' );
			}
		}
		$scanAction = $scanCon->buildScanAction( $scanActionVO );

		// ScanItems are stored separately
		$allItems = $scanAction->items;
		unset( $scanAction->items );

		$scanMeta = $scanAction->getRawData();
		unset( $scanMeta[ 'progress_callback' ] );
		$scanRecord->meta = $scanMeta;

		if ( empty( $allItems ) ) {
			( new SetScanCompleted() )->run( $scanID, $scanRecord, true );
			return;
		}

		$sliceSize = $scanCon->getQueueGroupSize();

		/** @var ScanItemsDB\Record $newRecord */
		$newRecord = $dbhItems->getRecord();
		$newRecord->scan_ref = $scanRecord->id;
		foreach ( \array_chunk( $allItems, $sliceSize ) as $chunk ) {
			$newRecord->items = $chunk;
			$newRecord->item_count = \count( $chunk );
			if ( !$dbhItems->getQueryInserter()->insert( $newRecord ) ) {
				throw new \RuntimeException( \sprintf( 'Failed to persist queue items for scan "%s".', $scanRecord->scan ) );
			}
			$scanAction->tickProgress();
		}

		( new RunState() )->markBuilt( $scanRecord );
		$this->verifyBuiltScanPersistence( $scanRecord );
	}

	private function verifyBuiltScanPersistence( ScansDB\Record $scanRecord ) :void {
		$rawMeta = $scanRecord->getRawData()[ 'meta' ] ?? null;
		$persisted = self::con()
			->db_con
			->scans
			->getQuerySelector()
			->byId( (int)$scanRecord->id );
		if ( !\is_string( $rawMeta )
			 || empty( $persisted )
			 || $persisted->status !== ScanStatus::BUILT
			 || ( $persisted->getRawData()[ 'meta' ] ?? null ) !== $rawMeta ) {
			throw new \RuntimeException( \sprintf(
				'Failed to persist built scan state for scan "%s".',
				$scanRecord->scan
			) );
		}
	}
}
