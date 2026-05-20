<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops as ResultItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
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

	public function testScanItemsSchemaDefaultsAttemptsToZeroInRealDb() :void {
		$scanID = $this->createScan( 'afs', 'built', [
			'ready_at'        => \time(),
			'last_process_at' => \time(),
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ] );

		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );

		$this->assertSame( 0, $item->attempts );
	}

	public function testRealDbWatchdogDoesNotResetFreshRunningWork() :void {
		$scanID = $this->createScan( 'afs', 'running', [
			'ready_at'        => \time() - 60,
			'last_process_at' => \time() - 30,
			'started_at'      => \time() - 60,
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ], \time() - 30, 0, 1 );

		( new QueueWatchdog() )->runIfStale();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'running', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertGreaterThan( 0, $item->started_at );
		$this->assertSame( 0, $item->finished_at );
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

		$watchdog->runIfStale();

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

	private function createScanItem( int $scanID, array $items, int $startedAt = 0, int $finishedAt = 0, int $attempts = 0 ) :int {
		/** @var ScanItemsDB\Handler $scanItems */
		$scanItems = $this->requireDb( 'scan_items' );
		/** @var ScanItemsDB\Record $record */
		$record = $scanItems->getRecord();
		$record->scan_ref = $scanID;
		$record->items = $items;
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
