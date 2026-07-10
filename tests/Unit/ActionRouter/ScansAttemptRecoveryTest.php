<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ScansAttemptRecovery,
	ScansBase
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\{
	QueueWatchdog,
	RunState
};
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

	public function test_explicit_recovery_action_forwards_positive_ids_and_returns_payload() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness );
		$watchdog = $this->installWatchdogSpy( $harness );

		foreach ( [ $scanID, (string)$scanID ] as $requestedScanID ) {
			$payload = $this->runScansAttemptRecovery( [
				'scan_id' => $requestedScanID,
			] )->response()->payload();

			$this->assertScanProgressPayloadContract( $payload );
			$this->assertSame( ScansBase::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] );
			$this->assertFalse( $payload[ 'failed' ] );
		}

		$this->assertSame( [ $scanID, $scanID ], $watchdog->receivedScanIDs );
	}

	/**
	 * @dataProvider invalidScanIDProvider
	 */
	public function test_explicit_recovery_action_rejects_invalid_ids_and_returns_payload( array $actionData ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$this->insertActiveScan( $harness );
		$watchdog = $this->installWatchdogSpy( $harness );

		$payload = $this->runScansAttemptRecovery( $actionData )->response()->payload();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( [], $watchdog->receivedScanIDs );
	}

	public function test_explicit_recovery_action_preserves_selected_id_in_failure_payload() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useNonEmptyModalRenderer( $harness );
		$harness->insertScan( [
			'scan'        => 'afs',
			'status'      => 'failed',
			'finished_at' => 1699999900,
			'meta'        => \base64_encode( \json_encode( [
				RunState::META_KEY_LAST_ERROR => 'unselected_failure',
			] ) ?: '[]' ),
		] );
		$selectedID = $harness->insertScan( [
			'scan'        => 'wpv',
			'status'      => 'failed',
			'finished_at' => 1699999950,
			'meta'        => \base64_encode( \json_encode( [
				RunState::META_KEY_LAST_ERROR => 'selected_failure',
			] ) ?: '[]' ),
		] );
		$watchdog = $this->installWatchdogSpy( $harness );

		$payload = $this->runScansAttemptRecovery( [
			'scan_id' => $selectedID,
		] )->response()->payload();

		$this->assertScanProgressPayloadContract( $payload );
		$this->assertSame( [ $selectedID ], $watchdog->receivedScanIDs );
		$this->assertTrue( $payload[ 'failed' ] );
		$this->assertSame( 'selected_failure', $payload[ 'failure_message' ] );
		$this->assertSame( ScansBase::SCAN_MODAL_STATE_FAILED, $payload[ 'modal_state' ] );
	}

	public function invalidScanIDProvider() :array {
		return [
			'missing scan id'    => [ [] ],
			'zero scan id'       => [ [ 'scan_id' => 0 ] ],
			'negative scan id'   => [ [ 'scan_id' => -10 ] ],
			'array scan id'      => [ [ 'scan_id' => [ 1 ] ] ],
			'nonnumeric scan id' => [ [ 'scan_id' => 'not-a-number' ] ],
			'fractional scan id' => [ [ 'scan_id' => '1.5' ] ],
			'float scan id'      => [ [ 'scan_id' => 1.0 ] ],
			'overflow scan id'   => [ [ 'scan_id' => (string)\PHP_INT_MAX.'0' ] ],
			'boolean scan id'    => [ [ 'scan_id' => true ] ],
		];
	}

	private function runScansAttemptRecovery( array $actionData = [] ) :ScansAttemptRecovery {
		$action = new ScansAttemptRecovery( $actionData );
		$method = new \ReflectionMethod( ScansAttemptRecovery::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );
		return $action;
	}

	private function insertActiveScan( ScanQueueLifecycleHarness $harness ) :int {
		return $harness->insertScan( [
			'scan'            => 'afs',
			'status'          => 'running',
			'ready_at'        => 1699999990,
			'last_process_at' => 1699999990,
			'started_at'      => 1699999990,
		] );
	}

	private function installWatchdogSpy( ScanQueueLifecycleHarness $harness ) :ScansAttemptRecoveryWatchdogSpy {
		$watchdog = new ScansAttemptRecoveryWatchdogSpy();
		$harness->controller->comps->scans_queue->watchdog = $watchdog;
		return $watchdog;
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
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertIsArray( $payload[ 'running' ] );
		$this->assertIsBool( $payload[ 'failed' ] );
		$this->assertIsString( $payload[ 'failure_message' ] );
		$this->assertIsArray( $payload[ 'scan_rows' ] );
		$this->assertContains( $payload[ 'modal_state' ], [
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
			public function render( string $unused, array $data ) :string {
				unset( $unused, $data );
				return 'rendered-modal';
			}
		};
	}
}

final class ScansAttemptRecoveryWatchdogSpy extends QueueWatchdog {

	/** @var list<int> */
	public array $receivedScanIDs = [];

	public function recoverScanIfStale( int $scanID ) :bool {
		$this->receivedScanIDs[] = $scanID;
		return false;
	}
}
