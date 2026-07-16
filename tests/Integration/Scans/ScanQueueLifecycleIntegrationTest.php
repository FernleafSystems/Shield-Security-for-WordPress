<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops as ResultItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Exceptions\NoQueueItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\CreateNewScan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	QueueItems,
	QueueMaintenance,
	QueueProcessor,
	QueueRecovery,
	QueueWatchdog,
	ReconcileQueue,
	RunState
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\BaseScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScanQueueLifecycleIntegrationTest extends ShieldIntegrationTestCase {

	public function testCreateNewScanPersistsFullLifecycleContractInRealSchema() :void {
		$this->requireDb( 'scans' );
		$before = \time();

		$created = ( new CreateNewScan() )->run( 'afs', 'full', '', 'manual' );

		$this->assertNotEmpty( $created );
		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( (int)$created->id );
		$this->assertSame( 'queued', $scan->status );
		$this->assertGreaterThanOrEqual( $before, $scan->created_at );
		$this->assertSame( 0, $scan->started_at );
		$this->assertSame( 0, $scan->last_process_at );
		$this->assertSame( 0, $scan->ready_at );
		$this->assertSame( 0, $scan->finished_at );
		$this->assertSame( [], $scan->meta );
		$this->assertSame( 'full', $scan->scope_type );
		$this->assertSame( '', $scan->scope_key );
		$this->assertSame( 'manual', $scan->run_trigger );
	}

	public function testScanItemsSchemaPersistsItemCountAndDefaultsAttemptsToZeroInRealDb() :void {
		$scanID = $this->createScan( 'afs', 'built', [
			'ready_at'        => \time(),
			'last_process_at' => \time(),
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ] );

		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );

		$this->assertSame( 0, $item->attempts );
		$this->assertSame( 1, $item->item_count );
	}

	public function testScanItemProgressUsesWorkUnitsAndPreservesRowCountSelectorsInRealDb() :void {
		$weightedScanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => \time(),
			'last_process_at' => \time(),
			'started_at'      => \time(),
		] );
		$largeItemID = $this->createScanItem( $weightedScanID, [ 'large-chunk' ], 0, \time(), 0, 80 );
		$this->createScanItem( $weightedScanID, [ 'small-chunk' ], 0, 0, 0, 1 );
		$legacyScanID = $this->createScan( 'wpv', 'built', [
			'ready_at'        => \time(),
			'last_process_at' => \time(),
		] );
		$this->createScanItem( $legacyScanID, [ 'legacy-row' ], 0, 0, 0, 0 );

		/** @var ScanItemsDB\Select $selector */
		$selector = $this->requireDb( 'scan_items' )->getQuerySelector();
		$progress = $selector->countProgressForEachScan();
		$allRows = $this->requireDb( 'scan_items' )->getQuerySelector()->countAllForEachScan();
		$unfinishedRows = $this->requireDb( 'scan_items' )->getQuerySelector()->countUnfinishedForEachScan();
		/** @var ScanItemsDB\Record $largeItem */
		$largeItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $largeItemID );

		$this->assertSame( 80, $largeItem->item_count );
		$this->assertSame( [ 'total' => 81, 'unfinished' => 1 ], $progress[ $weightedScanID ] );
		$this->assertSame( [ 'total' => 1, 'unfinished' => 1 ], $progress[ $legacyScanID ] );
		$this->assertSame( 2, (int)$allRows[ $weightedScanID ] );
		$this->assertSame( 1, (int)$unfinishedRows[ $weightedScanID ] );
		$this->assertSame( 1, (int)$allRows[ $legacyScanID ] );
		$this->assertSame( 1, (int)$unfinishedRows[ $legacyScanID ] );
	}

	public function testExplicitRecoveryAtomicallyClaimsOnlySameTimestampLowerIdHeadInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$lowerID = $this->createScan( 'afs', 'running', [
			'created_at'      => $staleAt,
			'ready_at'        => $staleAt,
			'last_process_at' => $staleAt,
			'started_at'      => $staleAt,
		] );
		$higherID = $this->createScan( 'wpv', 'running', [
			'created_at'      => $staleAt,
			'ready_at'        => $staleAt,
			'last_process_at' => $staleAt,
			'started_at'      => $staleAt,
		] );
		$waitingID = $this->createScan( 'apc', 'queued', [
			'created_at'      => $staleAt - 100,
			'last_process_at' => $staleAt,
		] );
		$lowerItemID = $this->createScanItem( $lowerID, [ 'afs-a' ], $staleAt, 0, 1 );
		$higherItemID = $this->createScanItem( $higherID, [ 'wpv-a' ], $staleAt, 0, 1 );
		$watchdog = new QueueWatchdog();

		$this->assertLessThan( $higherID, $lowerID );
		$this->assertFalse( $watchdog->recoverScanIfStale( $higherID ) );
		$this->assertTrue( $watchdog->recoverScanIfStale( $lowerID ) );
		$this->assertFalse( $watchdog->recoverScanIfStale( $lowerID ) );
		$this->assertFalse( $watchdog->recoverScanIfStale( $waitingID ) );

		/** @var ScansDB\Record $lower */
		$lower = $this->requireDb( 'scans' )->getQuerySelector()->byId( $lowerID );
		/** @var ScansDB\Record $higher */
		$higher = $this->requireDb( 'scans' )->getQuerySelector()->byId( $higherID );
		/** @var ScansDB\Record $waiting */
		$waiting = $this->requireDb( 'scans' )->getQuerySelector()->byId( $waitingID );
		/** @var ScanItemsDB\Record $lowerItem */
		$lowerItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $lowerItemID );
		/** @var ScanItemsDB\Record $higherItem */
		$higherItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $higherItemID );

		$this->assertGreaterThan( $staleAt, $lower->last_process_at );
		$this->assertSame( 0, $lowerItem->started_at );
		$this->assertSame( $staleAt, $higher->last_process_at );
		$this->assertSame( $staleAt, $higherItem->started_at );
		$this->assertSame( $staleAt, $waiting->last_process_at );
	}

	public function testRealDbWatchdogDoesNotResetFreshRunningWork() :void {
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => \time() - 60,
			'last_process_at' => \time() - 30,
			'started_at'      => \time() - 60,
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ], \time() - 30, 0, 1 );

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertGreaterThan( 0, $item->started_at );
		$this->assertSame( 0, $item->finished_at );
	}

	public function testScheduledWatchdogFinalizesLastActiveCronScanInRealDb() :void {
		$con = $this->requireController();
		$optionsSnapshot = $this->snapshotSelectedOptions( [ 'is_scan_cron' ] );
		$postScanHook = $con->prefix( 'post_scan' );
		$completedCalls = 0;
		$completedCallback = static function () use ( &$completedCalls ) :void {
			$completedCalls++;
		};
		\wp_clear_scheduled_hook( $postScanHook );
		\add_action( 'shield/scan_queue_completed', $completedCallback );

		try {
			$con->opts->optSet( 'is_scan_cron', true )->store();
			$scanID = $this->createScan( 'wpv', 'running', [
				'ready_at'        => \time() - 30,
				'last_process_at' => \time() - 30,
				'started_at'      => \time() - 30,
			] );
			$this->createScanItem( $scanID, [ 'wpv-a' ], 0, \time() - 10 );

			( new QueueWatchdog() )->runScheduled();

			/** @var ScansDB\Record $scan */
			$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
			$this->assertSame( 'completed', $scan->status );
			$this->assertSame(
				0,
				$this->requireDb( 'scan_items' )->getQuerySelector()->filterByScan( $scanID )->count()
			);
			$this->assertSame( 1, $completedCalls );
			$this->assertNotFalse( \wp_next_scheduled( $postScanHook ) );
			$this->assertFalse( $con->opts->optGet( 'is_scan_cron' ) );
		}
		finally {
			$this->restoreSelectedOptions( $optionsSnapshot );
			\remove_action( 'shield/scan_queue_completed', $completedCallback );
			\wp_clear_scheduled_hook( $postScanHook );
		}
	}

	public function testQueueMaintenanceCompletesReadyScanWithOnlyFinishedItemsInRealDb() :void {
		$before = \time();
		$scanID = $this->createScan( 'wpv', 'running', [
			'ready_at'        => \time() - 700,
			'last_process_at' => \time() - 700,
			'started_at'      => \time() - 700,
		] );
		$this->createScanItem( $scanID, [ 'wpv-a' ], 0, \time() - 300 );

		( new QueueMaintenance() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'completed', $scan->status );
		$this->assertGreaterThanOrEqual( $before, $scan->finished_at );
	}

	public function testQueueItemsNextUsesRealSqlToSelectOnlyReadyUnfinishedWork() :void {
		$queuedID = $this->createScan( 'afs', 'queued' );
		$this->createScanItem( $queuedID, [] );
		$buildingID = $this->createScan( 'apc', 'building' );
		$this->createScanItem( $buildingID, [] );
		$notReadyID = $this->createScan( 'wpv', 'built' );
		$this->createScanItem( $notReadyID, [] );
		$finishedID = $this->createScan( 'apc', 'built', [
			'ready_at'    => \time(),
			'finished_at' => \time(),
		] );
		$this->createScanItem( $finishedID, [] );
		$readyID = $this->createScan( 'wpv', 'built', [
			'ready_at'        => \time(),
			'last_process_at' => \time(),
		] );
		$finishedItemID = $this->createScanItem( $readyID, [ 'already-done.php' ], 0, \time() );
		$itemID = $this->createScanItem( $readyID, [] );

		$item = ( new QueueItems() )->next();

		$this->assertSame( $readyID, $item->scan_id );
		$this->assertNotSame( $finishedItemID, $item->qitem_id );
		$this->assertSame( $itemID, $item->qitem_id );
		$this->assertSame( 'wpv', $item->scan );
		$this->assertSame( 'full', $item->scope_type );
		$this->assertSame( '', $item->scope_key );
		$this->assertSame( 'manual', $item->run_trigger );
		$this->assertSame( 0, $item->scan_started_at );
		$this->assertSame( 1, $item->attempts );

		/** @var ScanItemsDB\Record $claimed */
		$claimed = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertGreaterThan( 0, $claimed->started_at );
		$this->assertSame( 1, $claimed->attempts );
	}

	public function testClaimedItemInOldestReadyScanBlocksNewerScanUntilCompletion() :void {
		$createdAt = \time() - 120;
		$oldScanID = $this->createScan( 'wpv', 'running', [
			'created_at' => $createdAt,
			'ready_at'   => $createdAt,
			'started_at' => $createdAt,
		] );
		$oldItemID = $this->createScanItem( $oldScanID, [ 'old' ], $createdAt, 0, 1 );
		$newScanID = $this->createScan( 'apc', 'built', [
			'created_at' => $createdAt + 30,
			'ready_at'   => $createdAt + 30,
		] );
		$newItemID = $this->createScanItem( $newScanID, [ 'new' ] );
		$queueItems = new QueueItems();

		$this->assertFalse( $queueItems->hasNextItem() );
		$noQueueItems = false;
		try {
			$queueItems->next();
		}
		catch ( NoQueueItems $e ) {
			$noQueueItems = true;
		}
		$this->assertTrue( $noQueueItems );
		/** @var ScanItemsDB\Record $newItem */
		$newItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $newItemID );
		$this->assertSame( 0, $newItem->started_at );

		$this->assertTrue( $this->requireDb( 'scan_items' )->getQueryUpdater()->updateById( $oldItemID, [
			'finished_at' => \time(),
		] ) );
		( new QueueMaintenance() )->run();

		/** @var ScansDB\Record $oldScan */
		$oldScan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $oldScanID );
		$this->assertSame( 'completed', $oldScan->status );
		$this->assertTrue( $queueItems->hasNextItem() );
		$this->assertSame( $newItemID, $queueItems->next()->qitem_id );
	}

	public function testNonterminalScanWithOnlyFinishedItemsBlocksUntilMaintenanceCompletesIt() :void {
		$createdAt = \time() - 120;
		$oldScanID = $this->createScan( 'wpv', 'running', [
			'created_at' => $createdAt,
			'ready_at'   => $createdAt,
			'started_at' => $createdAt,
		] );
		$this->createScanItem( $oldScanID, [ 'old' ], $createdAt, \time() - 30, 1 );
		$newScanID = $this->createScan( 'apc', 'built', [
			'created_at' => $createdAt + 30,
			'ready_at'   => $createdAt + 30,
		] );
		$newItemID = $this->createScanItem( $newScanID, [ 'new' ] );
		$queueItems = new QueueItems();

		$this->assertFalse( $queueItems->hasNextItem() );
		( new QueueMaintenance() )->run();

		/** @var ScansDB\Record $oldScan */
		$oldScan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $oldScanID );
		$this->assertSame( 'completed', $oldScan->status );
		$this->assertTrue( $queueItems->hasNextItem() );
		$this->assertSame( $newItemID, $queueItems->next()->qitem_id );
	}

	public function testOldestReadyScanSuppliesItsUnclaimedItemBeforeNewerScan() :void {
		$createdAt = \time() - 120;
		$oldScanID = $this->createScan( 'wpv', 'running', [
			'created_at' => $createdAt,
			'ready_at'   => $createdAt,
			'started_at' => $createdAt,
		] );
		$this->createScanItem( $oldScanID, [ 'claimed' ], $createdAt, 0, 1 );
		$oldUnclaimedItemID = $this->createScanItem( $oldScanID, [ 'unclaimed' ] );
		$newScanID = $this->createScan( 'apc', 'built', [
			'created_at' => $createdAt + 30,
			'ready_at'   => $createdAt + 30,
		] );
		$newItemID = $this->createScanItem( $newScanID, [ 'new' ] );

		$item = ( new QueueItems() )->next();

		$this->assertSame( $oldScanID, $item->scan_id );
		$this->assertSame( $oldUnclaimedItemID, $item->qitem_id );
		/** @var ScanItemsDB\Record $newItem */
		$newItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $newItemID );
		$this->assertSame( 0, $newItem->started_at );
	}

	public function testEqualScanCreationTimesUseScanIdThenItemId() :void {
		$createdAt = \time() - 120;
		$lowerScanID = $this->createScan( 'wpv', 'built', [
			'created_at' => $createdAt,
			'ready_at'   => $createdAt,
		] );
		$higherScanID = $this->createScan( 'apc', 'built', [
			'created_at' => $createdAt,
			'ready_at'   => $createdAt,
		] );
		$higherScanItemID = $this->createScanItem( $higherScanID, [ 'higher-scan' ] );
		$lowerFirstItemID = $this->createScanItem( $lowerScanID, [ 'lower-first' ] );
		$lowerSecondItemID = $this->createScanItem( $lowerScanID, [ 'lower-second' ] );

		$item = ( new QueueItems() )->next();

		$this->assertLessThan( $higherScanID, $lowerScanID );
		$this->assertLessThan( $lowerFirstItemID, $higherScanItemID );
		$this->assertLessThan( $lowerSecondItemID, $lowerFirstItemID );
		$this->assertSame( $lowerScanID, $item->scan_id );
		$this->assertSame( $lowerFirstItemID, $item->qitem_id );
	}

	public function testProcessorExpiredCleanupResetsStaleStartedItemsWithoutFailingRecoverableScan() :void {
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => \time() - 700,
			'last_process_at' => \time() - 700,
			'started_at'      => \time() - 700,
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ], \time() - 300 );

		( new QueueProcessor() )->handleExpiredItems();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertSame( 0, $item->started_at );
	}

	public function testRealDbWatchdogRecoversReportedDeadRunningScanShape() :void {
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => \time() - 700,
			'last_process_at' => \time() - 700,
			'started_at'      => \time() - 700,
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ] );

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		$this->assertArrayHasKey( RunState::META_KEY_WATCHDOG_RECOVERY, $scan->meta );

		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertSame( 0, $item->started_at );
		$this->assertSame( 0, $item->finished_at );
	}

	public function testRealDbWatchdogPreservesWaitingScansBehindRunningAfs() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$freshAt = \time() - 30;
		$afsID = $this->createScan( 'afs', 'running', [
			'created_at'      => $staleAt,
			'ready_at'        => $freshAt,
			'started_at'      => $freshAt,
			'last_process_at' => $freshAt,
		] );
		$this->createScanItem( $afsID, [ 'afs-a' ], $freshAt, 0, 1 );
		$apcID = $this->createScan( 'apc', 'built', [
			'created_at'      => $staleAt,
			'ready_at'        => $staleAt,
			'last_process_at' => $staleAt,
			'meta'            => $this->recoveryMeta( 1, $staleAt ),
		] );
		$apcItemID = $this->createScanItem( $apcID, [ 'apc-a' ] );
		$wpvID = $this->createScan( 'wpv', 'built', [
			'created_at'      => $staleAt,
			'ready_at'        => $staleAt,
			'last_process_at' => $staleAt,
			'meta'            => $this->recoveryMeta( 1, $staleAt ),
		] );
		$wpvItemID = $this->createScanItem( $wpvID, [ 'wpv-a' ] );

		( new QueueWatchdog() )->run();

		foreach ( [ $apcID => $apcItemID, $wpvID => $wpvItemID ] as $scanID => $itemID ) {
			/** @var ScansDB\Record $scan */
			$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( (int)$scanID );
			/** @var ScanItemsDB\Record $item */
			$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( (int)$itemID );
			$this->assertSame( 'built', $scan->status );
			$this->assertSame( 0, $scan->finished_at );
			$this->assertSame( 1, $scan->meta[ RunState::META_KEY_WATCHDOG_RECOVERY ][ 'attempts' ] ?? null );
			$this->assertSame( $staleAt, $scan->meta[ RunState::META_KEY_WATCHDOG_RECOVERY ][ 'last_attempt_at' ] ?? null );
			$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $scan->meta );
			$this->assertSame( 0, $item->started_at );
			$this->assertSame( 0, $item->finished_at );
		}
	}

	public function testRealDbSameTimestampScansUseQueueItemOrderForWaitingProtection() :void {
		$createdAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$freshAt = \time() - 30;
		$apcID = $this->createScan( 'apc', 'built', [
			'created_at'      => $createdAt,
			'ready_at'        => $createdAt,
			'last_process_at' => $createdAt,
			'meta'            => $this->recoveryMeta( 1, $createdAt ),
		] );
		$afsID = $this->createScan( 'afs', 'running', [
			'created_at'      => $createdAt,
			'ready_at'        => $freshAt,
			'started_at'      => $freshAt,
			'last_process_at' => $freshAt,
		] );
		$this->createScanItem( $afsID, [ 'afs-a' ], $freshAt, 0, 1 );
		$apcItemID = $this->createScanItem( $apcID, [ 'apc-a' ] );

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $apcID );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $apcItemID );
		$this->assertGreaterThan( $apcID, $afsID );
		$this->assertSame( 'built', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		$this->assertSame( 1, $scan->meta[ RunState::META_KEY_WATCHDOG_RECOVERY ][ 'attempts' ] ?? null );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $scan->meta );
		$this->assertSame( 0, $item->started_at );
		$this->assertSame( 0, $item->finished_at );
	}

	public function testWatchdogMarksStaleBuildingScanFailedInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'afs', 'building', [
			'created_at'      => $staleAt,
			'last_process_at' => $staleAt,
		] );

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'failed', $scan->status );
		$this->assertGreaterThan( 0, $scan->finished_at );
		$this->assertSame( ReconcileQueue::MESSAGE_TIMED_OUT, $scan->meta[ RunState::META_KEY_LAST_ERROR ] ?? '' );
	}

	public function testWatchdogRecoversStaleQueuedScanThroughRealSelectors() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'afs', 'queued', [
			'created_at' => $staleAt,
		] );
		$watchdog = new QueueWatchdog();

		$watchdog->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'queued', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		$this->assertSame(
			1,
			$this->requireDb( 'scans' )->getQuerySelector()
				 ->filterByScan( 'afs' )
				 ->filterByScope( 'full', '' )
				 ->filterByNotFinished()
				 ->count()
		);
		$this->assertNotFalse( \wp_next_scheduled( $watchdog->hook() ) );
	}

	public function testWatchdogResetsStaleClaimedItemInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => $staleAt,
			'started_at'      => $staleAt,
			'last_process_at' => $staleAt,
		] );
		$itemID = $this->createScanItem(
			$scanID,
			[ 'example.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS - 1
		);

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $item->started_at );
		$this->assertSame( QueueRecovery::MAX_ITEM_ATTEMPTS - 1, $item->attempts );
	}

	public function testWatchdogResetsAllStaleClaimedItemsInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'wpv', 'running', [
			'ready_at'        => $staleAt,
			'started_at'      => $staleAt,
			'last_process_at' => $staleAt,
		] );
		$firstItemID = $this->createScanItem(
			$scanID,
			[ 'wp-simple-firewall/icwp-wpsf.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS - 1
		);
		$secondItemID = $this->createScanItem(
			$scanID,
			[ 'two-factor/two-factor.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS - 1
		);

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		/** @var ScanItemsDB\Record $firstItem */
		$firstItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $firstItemID );
		/** @var ScanItemsDB\Record $secondItem */
		$secondItem = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $secondItemID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $firstItem->started_at );
		$this->assertSame( 0, $secondItem->started_at );
		$this->assertSame( QueueRecovery::MAX_ITEM_ATTEMPTS - 1, $firstItem->attempts );
		$this->assertSame( QueueRecovery::MAX_ITEM_ATTEMPTS - 1, $secondItem->attempts );
	}

	public function testWatchdogFailsExhaustedStaleRunningScanAndDeletesUnfinishedItemsInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => $staleAt,
			'started_at'      => $staleAt,
			'last_process_at' => $staleAt,
		] );
		$this->createScanItem(
			$scanID,
			[ 'example.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS
		);

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'failed', $scan->status );
		$this->assertGreaterThan( 0, $scan->finished_at );
		$this->assertSame( ReconcileQueue::MESSAGE_TIMED_OUT, $scan->meta[ RunState::META_KEY_LAST_ERROR ] ?? '' );
		$this->assertSame(
			0,
			$this->requireDb( 'scan_items' )->getQuerySelector()
				 ->filterByScan( $scanID )
				 ->filterByNotFinished()
				 ->count()
		);
	}

	public function testWatchdogFailsWhenLaterStaleClaimedItemIsExhaustedInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		$scanID = $this->createScan( 'wpv', 'running', [
			'ready_at'        => $staleAt,
			'started_at'      => $staleAt,
			'last_process_at' => $staleAt,
		] );
		$this->createScanItem(
			$scanID,
			[ 'wp-simple-firewall/icwp-wpsf.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS - 1
		);
		$this->createScanItem(
			$scanID,
			[ 'two-factor/two-factor.php' ],
			$staleAt,
			0,
			QueueRecovery::MAX_ITEM_ATTEMPTS
		);

		( new QueueWatchdog() )->run();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'failed', $scan->status );
		$this->assertGreaterThan( 0, $scan->finished_at );
		$this->assertSame( ReconcileQueue::MESSAGE_TIMED_OUT, $scan->meta[ RunState::META_KEY_LAST_ERROR ] ?? '' );
		$this->assertSame(
			0,
			$this->requireDb( 'scan_items' )->getQuerySelector()
				 ->filterByScan( $scanID )
				 ->filterByNotFinished()
				 ->count()
		);
	}

	public function testFailedStaleRowIsNotCountedAsActiveBlockerForSameSlug() :void {
		$scanID = $this->createScan( 'afs', 'failed', [
			'finished_at' => \time() - 60,
		] );

		$count = $this->requireDb( 'scans' )->getQuerySelector()
					  ->filterByScan( 'afs' )
					  ->filterByScope( 'full', '' )
					  ->filterByNotFinished()
					  ->count();

		$this->assertSame( 0, $count );
		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'failed', $scan->status );
	}

	public function testRealDbRepeatedWpvStartReturnsExistingActiveScanWithoutReplacement() :void {
		$wpvID = $this->createScan( 'wpv', 'running', [
			'ready_at'        => \time(),
			'started_at'      => \time(),
			'last_process_at' => \time(),
		] );
		$controller = new IntegrationScansControllerTestDouble( [
			'wpv' => new IntegrationScanControllerTestDouble( 'wpv' ),
		] );

		$result = $controller->startNewScans( [ 'wpv' ] );

		$this->assertSame( [ $wpvID ], $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$rows = $this->scanRowsForSlug( 'wpv' );
		$this->assertCount( 1, $rows );
		$this->assertSame( $wpvID, (int)$rows[ 0 ]->id );
		$this->assertSame( 'running', $rows[ 0 ]->status );
	}

	public function testRealDbRepeatedAllScansStartWithActiveRowsIsQuietAndDoesNotDuplicateRows() :void {
		$activeIDs = [
			'afs' => $this->createScan( 'afs', 'running', [
				'ready_at'        => \time(),
				'started_at'      => \time(),
				'last_process_at' => \time(),
			] ),
			'apc' => $this->createScan( 'apc', 'built', [
				'ready_at'        => \time(),
				'last_process_at' => \time(),
			] ),
			'wpv' => $this->createScan( 'wpv', 'queued' ),
		];
		$controller = new IntegrationScansControllerTestDouble( [
			'afs' => new IntegrationScanControllerTestDouble( 'afs' ),
			'apc' => new IntegrationScanControllerTestDouble( 'apc' ),
			'wpv' => new IntegrationScanControllerTestDouble( 'wpv' ),
		] );

		$result = $controller->startNewScans( [ 'afs', 'apc', 'wpv' ] );

		$this->assertSame( \array_values( $activeIDs ), $result->getStartedScanIDs() );
		$this->assertSame( [], $result->getFailures() );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueWatchdog() )->hook() ) );
		foreach ( $activeIDs as $slug => $id ) {
			$rows = $this->scanRowsForSlug( $slug );
			$this->assertCount( 1, $rows );
			$this->assertSame( $id, (int)$rows[ 0 ]->id );
		}
	}

	public function testPriorReleaseStalledRowsDoNotRemainPermanentActiveBlockersInRealDb() :void {
		$staleAt = \time() - QueueWatchdog::STALE_AFTER - 60;
		foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
			$scanID = $this->createScan( $slug, 'running', [
				'ready_at'        => $staleAt,
				'started_at'      => $staleAt,
				'last_process_at' => $staleAt,
			] );
			$this->createScanItem( $scanID, [ $slug.'-a' ], $staleAt, 0, QueueRecovery::MAX_ITEM_ATTEMPTS );
		}
		$controller = new IntegrationScansControllerTestDouble( [
			'afs' => new IntegrationScanControllerTestDouble( 'afs' ),
			'apc' => new IntegrationScanControllerTestDouble( 'apc' ),
			'wpv' => new IntegrationScanControllerTestDouble( 'wpv' ),
		] );

		$firstResult = $controller->startNewScans( [ 'afs', 'apc', 'wpv' ] );
		$secondResult = $controller->startNewScans( [ 'afs', 'apc', 'wpv' ] );

		$this->assertNotSame(
			[
				StartScansResult::REASON_ALREADY_EXISTS,
				StartScansResult::REASON_ALREADY_EXISTS,
				StartScansResult::REASON_ALREADY_EXISTS,
			],
			\array_column( $firstResult->getFailures(), 'reason' )
		);
		$startedSlugs = \array_values( \array_unique( \array_merge(
			$firstResult->getStartedSlugs(),
			$secondResult->getStartedSlugs()
		) ) );
		\sort( $startedSlugs );
		$this->assertSame( [ 'afs', 'apc', 'wpv' ], $startedSlugs );
		foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
			$rows = $this->scanRowsForSlug( $slug );
			$this->assertSame( 'failed', $rows[ 0 ]->status );
			$this->assertSame( 'queued', $rows[ 1 ]->status );
		}
	}

	private function createScan( string $slug, string $status, array $overrides = [] ) :int {
		/** @var ScansDB\Handler $scans */
		$scans = $this->requireDb( 'scans' );
		/** @var ScansDB\Record $record */
		$record = $scans->getRecord();
		$record->scan = $slug;
		$record->status = $status;
		$record->scope_type = 'full';
		$record->scope_key = '';
		$record->run_trigger = 'manual';
		$record->created_at = $overrides[ 'created_at' ] ?? \time();
		$record->started_at = $overrides[ 'started_at' ] ?? 0;
		$record->last_process_at = $overrides[ 'last_process_at' ] ?? 0;
		$record->ready_at = $overrides[ 'ready_at' ] ?? 0;
		$record->finished_at = $overrides[ 'finished_at' ] ?? 0;
		$record->meta = $overrides[ 'meta' ] ?? [];
		$this->assertTrue( $scans->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	private function createScanItem(
		int $scanID,
		array $items,
		int $startedAt = 0,
		int $finishedAt = 0,
		int $attempts = 0,
		?int $itemCount = null
	) :int {
		/** @var ScanItemsDB\Handler $scanItems */
		$scanItems = $this->requireDb( 'scan_items' );
		/** @var ScanItemsDB\Record $record */
		$record = $scanItems->getRecord();
		$record->scan_ref = $scanID;
		$record->items = $items;
		$record->item_count = $itemCount ?? \count( $items );
		$record->started_at = $startedAt;
		$record->attempts = $attempts;
		$record->finished_at = $finishedAt;
		$this->assertTrue( $scanItems->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	private function recoveryMeta( int $attempts, int $lastAttemptAt ) :array {
		return [
			RunState::META_KEY_WATCHDOG_RECOVERY => [
				'attempts'        => $attempts,
				'last_attempt_at' => $lastAttemptAt,
			],
		];
	}

	private function scanRowsForSlug( string $slug ) :array {
		return $this->requireDb( 'scans' )->getQuerySelector()
					->filterByScan( $slug )
					->filterByScope( 'full', '' )
					->setOrderBy( 'id', 'ASC', true )
					->queryWithResult();
	}
}

class IntegrationScansControllerTestDouble extends ScansController {

	private array $scanCons;

	public function __construct( array $scanCons ) {
		$this->scanCons = $scanCons;
	}

	public function getScanCon( string $slug ) {
		return $this->scanCons[ $slug ] ?? null;
	}

	public function canStartScans( bool $isCli = false ) :bool {
		unset( $isCli );
		return true;
	}
}

class IntegrationScanControllerTestDouble extends Base {

	private string $slug;

	public function __construct( string $slug ) {
		$this->slug = $slug;
	}

	public function getSlug() :string {
		return $this->slug;
	}

	public function isReady() :bool {
		return true;
	}

	protected function newItemActionHandler() {
		return null;
	}

	public function buildScanAction( ?BaseScanActionVO $scanAction = null ) {
		return $scanAction ?? $this->newScanActionVO();
	}

	public function buildScanResult( array $rawResult ) :ResultItemsDB\Record {
		unset( $rawResult );
		return new ResultItemsDB\Record();
	}
}
