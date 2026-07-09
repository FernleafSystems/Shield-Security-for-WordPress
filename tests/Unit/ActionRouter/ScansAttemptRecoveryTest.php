<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ScansAttemptRecovery,
	ScansBase,
	ScansCheck
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};

class ScansAttemptRecoveryTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->returnArg();
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_explicit_recovery_action_recovers_requested_stalled_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness, 1699999000 );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $scanID,
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( ScansBase::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] ?? '' );
		$this->assertFalse( $payload[ 'failed' ] ?? true );
		$this->assertSame( 1700000000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$item[ 'started_at' ] );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function test_explicit_recovery_action_recovers_all_claimed_items_for_requested_stalled_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => ScanStatus::RUNNING,
			'scope_type'      => 'full',
			'scope_key'       => '',
			'run_trigger'     => 'cli',
			'created_at'      => 1699999000,
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
		] );
		$firstItemID = $harness->insertScanItem(
			$scanID,
			[ 'wp-simple-firewall/icwp-wpsf.php' ],
			1699999000,
			0,
			1
		);
		$secondItemID = $harness->insertScanItem(
			$scanID,
			[ 'two-factor/two-factor.php' ],
			1699999000,
			0,
			1
		);
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $scanID,
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$firstItem = $harness->scanItemRow( $firstItemID );
		$secondItem = $harness->scanItemRow( $secondItemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( ScansBase::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] ?? '' );
		$this->assertFalse( $payload[ 'failed' ] ?? true );
		$this->assertSame( 1700000000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$firstItem[ 'started_at' ] );
		$this->assertSame( 0, (int)$secondItem[ 'started_at' ] );
		$this->assertSame( 1, (int)$firstItem[ 'attempts' ] );
		$this->assertSame( 1, (int)$secondItem[ 'attempts' ] );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertTrue( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function test_explicit_recovery_action_does_not_mutate_non_stalled_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness, 1699999990 );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999990, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $scanID,
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( 1699999990, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999990, (int)$item[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function test_explicit_recovery_action_does_not_mutate_waiting_scan_that_is_old() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$currentScanID = $this->insertActiveScan( $harness, 1699999990 );
		$waitingScanID = $harness->insertScan( [
			'scan'            => 'wpv',
			'status'          => ScanStatus::QUEUED,
			'scope_type'      => 'plugin',
			'scope_key'       => 'shield-security',
			'created_at'      => 1699999000,
			'last_process_at' => 0,
		] );
		$currentItemID = $harness->insertScanItem( $currentScanID, [ 'afs-a' ], 1699999990, 0, 1 );
		$waitingItemID = $harness->insertScanItem( $waitingScanID, [ 'wpv-a' ], 0, 0, 0 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $waitingScanID,
		] )->response()->payload();
		$currentScan = $harness->scanRow( $currentScanID );
		$waitingScan = $harness->scanRow( $waitingScanID );
		$currentItem = $harness->scanItemRow( $currentItemID );
		$waitingItem = $harness->scanItemRow( $waitingItemID );
		$queries = $harness->sql->queryLog();
		$rows = $payload[ 'scan_rows' ] ?? [];

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertCount( 2, $rows );
		$this->assertSame( $currentScanID, $rows[ 0 ][ 'id' ] );
		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertSame( $waitingScanID, $rows[ 1 ][ 'id' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 1 ][ 'is_stale' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertSame( 0, $rows[ 1 ][ 'progress' ] );
		$this->assertSame( 1699999990, (int)$currentScan[ 'last_process_at' ] );
		$this->assertSame( 0, (int)$waitingScan[ 'last_process_at' ] );
		$this->assertSame( 1699999990, (int)$currentItem[ 'started_at' ] );
		$this->assertSame( 0, (int)$waitingItem[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	/**
	 * @dataProvider invalidScanIDProvider
	 */
	public function test_explicit_recovery_action_does_not_mutate_for_invalid_scan_id( array $actionData ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness, 1699999000 );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( $actionData )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999000, (int)$item[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function test_explicit_recovery_action_does_not_mutate_unknown_scan_id() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness, 1699999000 );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $scanID + 1000,
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999000, (int)$item[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	/**
	 * @dataProvider terminalScanProvider
	 */
	public function test_explicit_recovery_action_does_not_mutate_terminal_scan( string $status ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => $status,
			'ready_at'        => 1699999000,
			'last_process_at' => 1699999000,
			'started_at'      => 1699999000,
			'finished_at'     => 1700000000,
		] );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $scanID,
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999000, (int)$item[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function test_scans_check_does_not_attempt_recovery_for_stalled_scan() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness, 1699999000 );
		$itemID = $harness->insertScanItem( $scanID, [ 'afs-a' ], 1699999000, 0, 1 );
		$harness->async->resetTransport();
		$harness->sql->resetQueryLog();

		$payload = $this->runScansCheck( [
			'scan_ids' => [ $scanID ],
		] )->response()->payload();
		$scan = $harness->scanRow( $scanID );
		$item = $harness->scanItemRow( $itemID );
		$queries = $harness->sql->queryLog();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( ScansBase::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] ?? '' );
		$this->assertSame( 1699999000, (int)$scan[ 'last_process_at' ] );
		$this->assertSame( 1699999000, (int)$item[ 'started_at' ] );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scan_items`' ) );
		$this->assertFalse( $this->queryLogContains( $queries, 'UPDATE `scans`' ) );
	}

	public function invalidScanIDProvider() :array {
		return [
			'missing scan id' => [ [] ],
			'zero scan id'    => [ [ 'scan_id' => 0 ] ],
			'negative scan id'=> [ [ 'scan_id' => -10 ] ],
			'array scan id'   => [ [ 'scan_id' => [ 1 ] ] ],
		];
	}

	public function terminalScanProvider() :array {
		return [
			'finished' => [ ScanStatus::COMPLETED ],
			'failed'   => [ ScanStatus::FAILED ],
		];
	}

	private function runScansAttemptRecovery( array $actionData = [] ) :ScansAttemptRecovery {
		$action = new ScansAttemptRecovery( $actionData );
		$this->invokeExec( $action, ScansAttemptRecovery::class );
		return $action;
	}

	private function runScansCheck( array $actionData = [] ) :ScansCheck {
		$action = new ScansCheck( $actionData );
		$this->invokeExec( $action, ScansCheck::class );
		return $action;
	}

	private function invokeExec( object $action, string $class ) :void {
		$method = new \ReflectionMethod( $class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );
	}

	private function insertActiveScan( ScanQueueLifecycleHarness $harness, int $lastProcessAt ) :int {
		return $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => ScanStatus::RUNNING,
			'ready_at'        => $lastProcessAt,
			'last_process_at' => $lastProcessAt,
			'started_at'      => $lastProcessAt,
		] );
	}

	private function assertScanProgressPayloadContract( array $payload ) :void {
		$this->assertEqualsCanonicalizing( [
			'failed',
			'failure_message',
			'modal_html',
			'modal_state',
			'running',
			'scan_rows',
			'success',
		], \array_keys( $payload ) );
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertIsArray( $payload[ 'running' ] ?? null );
		$this->assertIsBool( $payload[ 'failed' ] ?? null );
		$this->assertIsString( $payload[ 'failure_message' ] ?? null );
		$this->assertIsArray( $payload[ 'scan_rows' ] ?? null );
		$this->assertContains( $payload[ 'modal_state' ] ?? '', [
			ScansBase::SCAN_MODAL_STATE_INITIATING,
			ScansBase::SCAN_MODAL_STATE_RUNNING,
			ScansBase::SCAN_MODAL_STATE_COMPLETED,
			ScansBase::SCAN_MODAL_STATE_FAILED,
		] );
		$this->assertArrayHasKey( 'modal_html', $payload );
		$this->assertIsString( $payload[ 'modal_html' ] );
		$this->assertNotSame( '', $payload[ 'modal_html' ] );
		$this->assertArrayNotHasKey( 'vars', $payload );
	}

	private function useNonEmptyModalRenderer( ScanQueueLifecycleHarness $harness ) :void {
		$harness->controller->action_router = new class {
			public array $renderData = [];

			public function render( string $unused, array $data ) :string {
				unset( $unused );
				$this->renderData = $data;
				return 'rendered-modal';
			}
		};
	}

	private function queryLogContains( array $queries, string $needle ) :bool {
		foreach ( $queries as $query ) {
			if ( \strpos( $query, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
