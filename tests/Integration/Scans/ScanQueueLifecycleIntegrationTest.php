<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ScanItems\Ops as ScanItemsDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Scans\Ops as ScansDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\CreateNewScan;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CleanQueue,
	QueueItems,
	QueueProcessor
};
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

	public function testCleanQueueDoesNotFailBuiltScanWithUnfinishedItemsInRealDb() :void {
		$scanID = $this->createScan( 'afs', 'built', [
			'ready_at'        => \time() - 700,
			'last_process_at' => \time() - 700,
		] );
		$itemID = $this->createScanItem( $scanID, [ 'example.php' ], \time() - 300 );

		( new CleanQueue() )->execute();

		/** @var ScansDB\Record $scan */
		$scan = $this->requireDb( 'scans' )->getQuerySelector()->byId( $scanID );
		$this->assertSame( 'built', $scan->status );
		$this->assertSame( 0, $scan->finished_at );
		/** @var ScanItemsDB\Record $item */
		$item = $this->requireDb( 'scan_items' )->getQuerySelector()->byId( $itemID );
		$this->assertSame( 0, $item->started_at );
		$this->assertSame( 0, $item->finished_at );
	}

	public function testCleanQueueCompletesStaleReadyScanWithOnlyFinishedItemsInRealDb() :void {
		$before = \time();
		$scanID = $this->createScan( 'wpv', 'running', [
			'ready_at'        => \time() - 700,
			'last_process_at' => \time() - 700,
			'started_at'      => \time() - 700,
		] );
		$this->createScanItem( $scanID, [ 'wpv-a' ], 0, \time() - 300 );

		( new CleanQueue() )->execute();

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
		$this->assertTrue( $item->is_last_item_for_scan );
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
		$record->meta = [];
		$this->assertTrue( $scans->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	private function createScanItem( int $scanID, array $items, int $startedAt = 0, int $finishedAt = 0 ) :int {
		/** @var ScanItemsDB\Handler $scanItems */
		$scanItems = $this->requireDb( 'scan_items' );
		/** @var ScanItemsDB\Record $record */
		$record = $scanItems->getRecord();
		$record->scan_ref = $scanID;
		$record->items = $items;
		$record->started_at = $startedAt;
		$record->finished_at = $finishedAt;
		$this->assertTrue( $scanItems->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}
}
