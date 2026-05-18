<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

if ( !\function_exists( __NAMESPACE__.'\\error_log' ) ) {
	function error_log( string $message ) :bool {
		\FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\QueueLifecycleLogSpy::record( $message );
		return true;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\{
	ScansController,
	StartScansResult
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginDeactivate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CompleteQueue,
	Controller as QueueController,
	ProcessQueueItem,
	QueueInit,
	QueueItems,
	QueueMaintenance,
	QueueProcessor,
	QueueWatchdog,
	RunState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\{
	QueueLifecycleLogSpy,
	ScanQueueLifecycleHarness
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};

class ScanQueueAsyncLifecycleTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		QueueLifecycleLogSpy::reset();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_wp_loaded_does_not_orchestrate_scan_queue_work_during_ordinary_requests() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'afs',
			'status' => 'queued',
		] );
		$readyScanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
		] );
		$harness->insertScanItem( $readyScanID, [ 'wpv-a' ] );
		$harness->sql->resetQueryLog();
		$harness->async->resetTransport();

		( new QueueController() )->onWpLoaded();

		$this->assertSame( [], $harness->sql->queryLog() );
		$this->assertSame( [], $harness->async->scheduled );
		$this->assertSame( [], $harness->async->remotePosts );
	}

	public function test_manual_start_creates_fresh_queued_rows_with_complete_producer_contract() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();

		$result = ( new LifecycleScansControllerTestDouble() )->startNewScans( [ 'afs', 'apc', 'wpv' ] );

		$this->assertInstanceOf( StartScansResult::class, $result );
		$this->assertSame( [ 1, 2, 3 ], $result->getStartedScanIDs() );
		$this->assertCount( 3, $harness->scansDb->rawInserts );

		foreach ( $harness->scansDb->rawInserts as $rawInsert ) {
			$this->assertSame( 'queued', $rawInsert[ 'status' ] ?? null );
			$this->assertSame( 1700000000, $rawInsert[ 'created_at' ] ?? null );
			$this->assertSame( 0, $rawInsert[ 'started_at' ] ?? null );
			$this->assertSame( 0, $rawInsert[ 'last_process_at' ] ?? null );
			$this->assertSame( 0, $rawInsert[ 'ready_at' ] ?? null );
			$this->assertSame( 0, $rawInsert[ 'finished_at' ] ?? null );
			$this->assertSame( 'full', $rawInsert[ 'scope_type' ] ?? null );
			$this->assertSame( '', $rawInsert[ 'scope_key' ] ?? null );
			$this->assertSame( 'manual', $rawInsert[ 'run_trigger' ] ?? null );
			$this->assertSame( [], \json_decode( \base64_decode( (string)( $rawInsert[ 'meta' ] ?? '' ) ), true ) );
		}
		$this->assertFalse( $this->queryLogContains( $harness->sql->queryLog(), 'SELECT * FROM `scans` WHERE `id`' ) );
	}

	public function test_queue_init_rejects_failed_or_finished_scan_rows() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'        => 'afs',
			'status'      => 'failed',
			'finished_at' => 1699999900,
		] );

		$this->assertFalse( ( new QueueInit() )->init( $scanID ) );

		$row = $harness->scanRow( $scanID );
		$this->assertSame( 'failed', $row[ 'status' ] );
		$this->assertSame( 1699999900, (int)$row[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
	}

	public function test_builder_healthcheck_builds_all_selected_manual_scans_and_does_not_stop_after_first_active_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();

		( new LifecycleScansControllerTestDouble() )->startNewScans( [ 'afs', 'apc', 'wpv' ] );

		$harness->builder()->handle_cron_healthcheck();

		$rowsByScan = [];
		foreach ( $harness->scanRows() as $row ) {
			$rowsByScan[ $row[ 'scan' ] ] = $row;
		}
		$this->assertSame( [ 'afs', 'apc', 'wpv' ], \array_keys( $rowsByScan ) );
		foreach ( [ 'afs', 'apc', 'wpv' ] as $slug ) {
			$this->assertSame( 'built', $rowsByScan[ $slug ][ 'status' ], $slug.' should be built' );
			$this->assertSame( 0, (int)$rowsByScan[ $slug ][ 'finished_at' ], $slug.' should remain unfinished' );
			$this->assertGreaterThan( 0, $harness->countScanItems( (int)$rowsByScan[ $slug ][ 'id' ] ), $slug.' should have queue items' );
		}
		$this->assertTrue( $harness->async->hasScheduledHook( 'icwp_wpsf_shield_scanq_cron' ) );
	}

	public function test_builder_output_is_visible_to_processor_queue_selection() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		( new LifecycleScansControllerTestDouble() )->startNewScans( [ 'afs', 'apc', 'wpv' ] );
		$harness->builder()->handle_cron_healthcheck();

		$item = ( new QueueItems() )->next();

		$this->assertSame( 'afs', $item->scan );
		$this->assertSame( 1, $item->scan_id );
		$this->assertSame( 1, $item->qitem_id );
		$this->assertSame( 'full', $item->scope_type );
		$this->assertSame( '', $item->scope_key );
		$this->assertSame( 'manual', $item->run_trigger );
		$this->assertSame( 0, $item->scan_started_at );
	}

	public function test_queue_init_marks_building_without_reloading_known_scan_row() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'   => 'afs',
			'status' => 'queued',
		] );
		$harness->sql->resetQueryLog();

		$this->assertTrue( ( new QueueInit() )->init( $scanID ) );

		$this->assertSame( 1, $this->queryLogCount( $harness->sql->queryLog(), 'SELECT * FROM `scans` WHERE `id`' ) );
		$this->assertSame( 'built', $harness->scanRow( $scanID )[ 'status' ] );
	}

	public function test_processor_healthcheck_completes_ready_queue_work() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [] );
		$harness->sql->resetQueryLog();

		$harness->processor()->handle_cron_healthcheck();

		$this->assertSame( 'completed', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 1700000000, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertSame( [], $harness->scanItemRow( $itemID ) );
		$this->assertSame( 1, $this->queryLogCount( $harness->sql->queryLog(), 'UPDATE `scan_items` SET `finished_at`' ) );
	}

	public function test_processor_attempts_idempotent_completion_for_non_final_queue_item() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
		] );
		$harness->insertScanItem( $scanID, [] );
		$harness->insertScanItem( $scanID, [] );
		$harness->sql->resetQueryLog();

		( new ProcessQueueItem() )->run( ( new QueueItems() )->next() );

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'running', $scan[ 'status' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 2, $harness->countScanItems( $scanID ) );
		$this->assertTrue( $this->queryLogContains( $harness->sql->queryLog(), "`status`='completed'" ) );
	}

	public function test_complete_queue_completes_active_scan_with_finished_items_before_deleting_queue_rows() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
			'started_at'      => 1700000000,
		] );
		$harness->insertScanItem( $scanID, [ 'wpv-a' ], 0, 1700000000 );
		$harness->sql->resetQueryLog();

		( new CompleteQueue() )->complete();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
	}

	public function test_complete_queue_fails_ready_scan_without_queue_rows_immediately() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
			'started_at'      => 1700000000,
		] );

		( new CompleteQueue() )->complete();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'failed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$this->assertArrayHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
	}

	public function test_processor_expired_cleanup_recovers_built_work_with_items_instead_of_failing_it() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000 );

		( new QueueProcessor() )->handleExpiredItems();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'built', $scan[ 'status' ] );
		$this->assertSame( 0, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1, $harness->countScanItems( $scanID ) );
		$this->assertSame( 0, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
	}

	public function test_watchdog_leaves_fresh_running_scan_untouched() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999900,
			'last_process_at' => 1699999950,
			'started_at'      => 1699999900,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999900 );
		$harness->sql->resetQueryLog();

		( new QueueWatchdog() )->runIfStale();

		$this->assertSame( 'running', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 1699999950, (int)$harness->scanRow( $scanID )[ 'last_process_at' ] );
		$this->assertSame( 1699999900, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $harness->sql->queryLog(), 'UPDATE `scan_items` SET `started_at`=0' ) );
	}

	public function test_watchdog_leaves_fresh_building_scan_without_heartbeat_untouched() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'building',
			'created_at'      => 1699999950,
			'last_process_at' => 0,
		] );

		( new QueueWatchdog() )->run();

		$this->assertSame( 'building', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
	}

	public function test_watchdog_resets_only_one_stale_claimed_item_when_attempts_remain() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$claimedItemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$otherItemID = $harness->insertScanItem( $scanID, [ 'afs-b' ], 0, 0, 0 );

		( new QueueWatchdog() )->run();

		$this->assertSame( 'running', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanItemRow( $claimedItemID )[ 'started_at' ] );
		$this->assertSame( 0, (int)$harness->scanItemRow( $claimedItemID )[ 'finished_at' ] );
		$this->assertSame( 0, (int)$harness->scanItemRow( $otherItemID )[ 'started_at' ] );
		$this->assertSame( 2, $harness->countScanItems( $scanID ) );
	}

	public function test_watchdog_fails_scan_when_stale_claimed_item_exhausted_attempts() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 2 );

		( new QueueWatchdog() )->run();

		$this->assertSame( 'failed', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 1700000000, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$this->assertArrayHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $harness->scanRow( $scanID ) ) );
	}

	public function test_watchdog_recovers_reported_dead_running_scan_shape_without_existing_cron() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a', 'afs-b' ] );
		$harness->async->resetTransport();

		$watchdog = new QueueWatchdog();
		$watchdog->runScheduled();

		$this->assertSame( 'running', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertSame( 0, (int)$harness->scanItemRow( $itemID )[ 'started_at' ] );
		$this->assertTrue( $harness->async->hasScheduledHook( $watchdog->hook() ) );
	}

	public function test_watchdog_fails_unstarted_stale_scan_after_resume_attempts_are_exhausted() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
			'meta'            => base64_encode( json_encode( [
				RunState::META_KEY_WATCHDOG_RECOVERY => [
					'attempts'        => 1,
					'last_attempt_at' => 1699999000,
				],
			] ) ),
		] );
		$harness->insertScanItem( $scanID, [ 'afs-a' ] );

		( new QueueWatchdog() )->run();

		$this->assertSame( 'failed', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
	}

	public function test_watchdog_unschedules_when_no_active_scans_remain() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$watchdog = new QueueWatchdog();
		$harness->async->scheduleEvent( 1700000060, $watchdog->hook(), 'single' );

		$watchdog->scheduleIfActive();

		$this->assertFalse( $harness->async->hasScheduledHook( $watchdog->hook() ) );
	}

	public function test_queue_maintenance_completes_ready_scan_when_all_items_are_finished() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$harness->insertScanItem( $scanID, [ 'wpv-a' ], 0, 1699999100 );
		$harness->sql->resetQueryLog();

		( new QueueMaintenance() )->run();
		$maintenanceQueries = $harness->sql->queryLog();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1, $harness->countScanItems( $scanID ) );
		$this->assertArrayNotHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $scan ) );
		$this->assertFalse( $this->queryLogContains( $maintenanceQueries, 'UPDATE `scan_items` SET `started_at`=0' ) );
	}

	public function test_watchdog_fails_only_irrecoverable_ready_scan_without_items_and_stale_building_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$readyWithoutItems = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'built',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
		] );
		$staleBuilding = $harness->insertScan( [
			'scan'            => 'apc',
			'status'          => 'building',
			'last_process_at' => 1699999000,
		] );

		( new QueueWatchdog() )->run();

		$readyScan = $harness->scanRow( $readyWithoutItems );
		$buildingScan = $harness->scanRow( $staleBuilding );
		$this->assertSame( 'failed', $readyScan[ 'status' ] );
		$this->assertSame( 'failed', $buildingScan[ 'status' ] );
		$this->assertArrayHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $readyScan ) );
		$this->assertArrayHasKey( RunState::META_KEY_LAST_ERROR, $this->scanMeta( $buildingScan ) );
	}

	public function test_plugin_deactivation_marks_unfinished_scans_failed_before_dropping_scan_tables() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
			'started_at'      => 1700000000,
		] );
		$harness->insertScanItem( $scanID, [ 'wpv-a' ], 1700000000 );
		$harness->insertScan( [
			'scan'        => 'afs',
			'status'      => 'completed',
			'finished_at' => 1700000000,
		] );
		$harness->sql->resetQueryLog();

		$method = new \ReflectionMethod( PluginDeactivate::class, 'purgeScans' );
		$method->setAccessible( true );
		$method->invoke( new PluginDeactivate() );
		$queries = $harness->sql->queryLog();

		$this->assertSame( 'failed', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 0, $harness->countScanItems( $scanID ) );
		$scanFailedAt = $this->queryLogFirstIndex( $queries, 'UPDATE `scans` SET' );
		$scanItemsDroppedAt = $this->queryLogLastIndex( $queries, 'DELETE FROM `scan_items`' );
		$scanResultsDroppedAt = $this->queryLogFirstIndex( $queries, 'DELETE FROM `scan_results`' );

		$this->assertGreaterThanOrEqual( 0, $scanFailedAt );
		$this->assertGreaterThan( $scanFailedAt, $scanItemsDroppedAt );
		$this->assertGreaterThan( $scanFailedAt, $scanResultsDroppedAt );
	}

	public function test_locked_builder_dispatch_schedules_normal_builder_healthcheck_when_queued_work_exists() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'afs',
			'status' => 'queued',
		] );
		set_site_transient( 'icwp_wpsf_shield_scanqbuild_process_lock', 'locked', 60 );

		$harness->builder()->dispatch();

		$this->assertTrue( $harness->async->hasScheduledHook( 'icwp_wpsf_shield_scanqbuild_cron' ) );
	}

	public function test_locked_processor_dispatch_schedules_normal_processor_healthcheck_when_ready_items_exist() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'built',
			'ready_at'        => 1700000000,
			'last_process_at' => 1700000000,
		] );
		$harness->insertScanItem( $scanID, [] );
		set_site_transient( 'icwp_wpsf_shield_scanq_process_lock', 'locked', 60 );

		$harness->processor()->dispatch();

		$this->assertTrue( $harness->async->hasScheduledHook( 'icwp_wpsf_shield_scanq_cron' ) );
		$this->assertTrue( $harness->async->hasScheduledHook( 'icwp_wpsf_shield_scanq_expired_cron' ) );
	}

	public function test_builder_has_no_expired_cleanup_authority() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$harness->insertScan( [
			'scan'   => 'afs',
			'status' => 'queued',
		] );

		$harness->builder()->dispatch();

		$this->assertFalse( $harness->async->hasScheduledHook( 'icwp_wpsf_shield_scanqbuild_expired_cron' ) );
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		return $this->queryLogCount( $queries, $needle ) > 0;
	}

	private function queryLogCount( array $queries, string $needle ) :int {
		$count = 0;
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				$count++;
			}
		}
		return $count;
	}

	private function queryLogFirstIndex( array $queries, string $needle ) :int {
		foreach ( $queries as $index => $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				return (int)$index;
			}
		}
		return -1;
	}

	private function queryLogLastIndex( array $queries, string $needle ) :int {
		$match = -1;
		foreach ( $queries as $index => $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				$match = (int)$index;
			}
		}
		return $match;
	}

	private function scanMeta( array $scan ) :array {
		return \json_decode( \base64_decode( (string)( $scan[ 'meta' ] ?? '' ) ), true ) ?: [];
	}
}

class LifecycleScansControllerTestDouble extends ScansController {

	public function getScanCon( string $slug ) {
		return self::con()->comps->scans->getScanCon( $slug );
	}

	public function canStartScans( bool $isCli = false ) :bool {
		unset( $isCli );
		return true;
	}
}
