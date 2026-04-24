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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	CleanQueue,
	Controller as QueueController,
	QueueInit,
	QueueItems,
	QueueProcessor
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

		$harness->processor()->handle_cron_healthcheck();

		$this->assertSame( 'completed', $harness->scanRow( $scanID )[ 'status' ] );
		$this->assertSame( 1700000000, (int)$harness->scanRow( $scanID )[ 'finished_at' ] );
		$this->assertSame( [], $harness->scanItemRow( $itemID ) );
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
		$this->assertFalse( QueueLifecycleLogSpy::contains( 'Scan timed out before it could finish' ) );
	}

	public function test_clean_queue_completes_stale_ready_scan_when_all_items_are_finished() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => 'running',
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$harness->insertScanItem( $scanID, [ 'wpv-a' ], 0, 1699999100 );

		( new CleanQueue() )->execute();

		$scan = $harness->scanRow( $scanID );
		$this->assertSame( 'completed', $scan[ 'status' ] );
		$this->assertSame( 1700000000, (int)$scan[ 'finished_at' ] );
		$this->assertSame( 1, $harness->countScanItems( $scanID ) );
		$this->assertFalse( QueueLifecycleLogSpy::contains( 'Scan timed out before it could finish' ) );
	}

	public function test_clean_queue_fails_only_irrecoverable_ready_scan_without_items_and_stale_building_scan() :void {
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

		( new CleanQueue() )->execute();

		$this->assertSame( 'failed', $harness->scanRow( $readyWithoutItems )[ 'status' ] );
		$this->assertSame( 'failed', $harness->scanRow( $staleBuilding )[ 'status' ] );
		$this->assertTrue( QueueLifecycleLogSpy::contains( 'scan_id='.$readyWithoutItems ) );
		$this->assertTrue( QueueLifecycleLogSpy::contains( 'scan_id='.$staleBuilding ) );
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
