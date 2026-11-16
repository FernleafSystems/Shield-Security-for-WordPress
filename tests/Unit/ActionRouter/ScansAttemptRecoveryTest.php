<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansAttemptRecovery;
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

	public function test_recovery_forwards_positive_ids_and_reports_progress() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useRecordingRenderer( $harness );
		$scanID = $this->insertActiveScan( $harness );
		$watchdog = $this->installWatchdogSpy( $harness );

		foreach ( [ $scanID, (string)$scanID ] as $requestedScanID ) {
			$payload = $this->runScansAttemptRecovery( [
				'scan_id' => $requestedScanID,
			] )->response()->payload();

			$this->assertProgressPayload( $payload, ScansProgress::STATE_RUNNING );
		}

		$this->assertSame( [ $scanID, $scanID ], $watchdog->receivedScanIDs );
	}

	/**
	 * @dataProvider invalidScanIDProvider
	 */
	public function test_recovery_ignores_invalid_ids_and_still_reports_progress( array $actionData ) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$this->useRecordingRenderer( $harness );
		$this->insertActiveScan( $harness );
		$watchdog = $this->installWatchdogSpy( $harness );

		$payload = $this->runScansAttemptRecovery( $actionData )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_RUNNING );
		$this->assertSame( [], $watchdog->receivedScanIDs );
	}

	public function test_recovered_scan_that_failed_is_reported_with_its_reason() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$renderer = $this->useRecordingRenderer( $harness );
		$this->insertFailedScan( $harness, 'afs', 'unselected_failure' );
		$selectedID = $this->insertFailedScan( $harness, 'wpv', 'selected_failure' );
		$watchdog = $this->installWatchdogSpy( $harness );

		// The page follows the scan it recovers.
		$payload = $this->runScansAttemptRecovery( [
			'scan_id'  => $selectedID,
			'scan_ids' => [ $selectedID ],
		] )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_FAILED );
		$this->assertSame( [ $selectedID ], $watchdog->receivedScanIDs );
		$this->assertSame( 'selected_failure', $renderer->renderData[ 'failure_message' ] );
	}

	public function test_recovery_reports_every_scan_the_page_is_following() :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		$renderer = $this->useRecordingRenderer( $harness );
		$recoveredID = $this->insertActiveScan( $harness );
		$trackedFailedID = $this->insertFailedScan( $harness, 'wpv', 'tracked_failure' );
		$this->installWatchdogSpy( $harness );

		$payload = $this->runScansAttemptRecovery( [
			'scan_id'  => $recoveredID,
			'scan_ids' => [ $trackedFailedID, $recoveredID ],
		] )->response()->payload();

		$this->assertProgressPayload( $payload, ScansProgress::STATE_FAILED );
		$this->assertSame( 'tracked_failure', $renderer->renderData[ 'failure_message' ] );
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

	private function insertFailedScan( ScanQueueLifecycleHarness $harness, string $scan, string $error ) :int {
		return $harness->insertScan( [
			'scan'        => $scan,
			'status'      => 'failed',
			'finished_at' => 1699999900,
			'meta'        => \base64_encode( \json_encode( [
				RunState::META_KEY_LAST_ERROR => $error,
			] ) ?: '[]' ),
		] );
	}

	private function installWatchdogSpy( ScanQueueLifecycleHarness $harness ) :ScansAttemptRecoveryWatchdogSpy {
		$watchdog = new ScansAttemptRecoveryWatchdogSpy();
		$harness->controller->comps->scans_queue->watchdog = $watchdog;
		return $watchdog;
	}

	private function assertProgressPayload( array $payload, string $state ) :void {
		$this->assertEqualsCanonicalizing( [ 'progress_detail', 'progress_html', 'progress_state', 'success' ], \array_keys( $payload ) );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( $state, $payload[ 'progress_state' ] );
		$this->assertSame( 'rendered-progress', $payload[ 'progress_html' ] );
	}

	private function useRecordingRenderer( ScanQueueLifecycleHarness $harness ) :object {
		return $harness->controller->action_router = new class {
			public array $renderData = [];

			public function render( string $unused, array $data ) :string {
				unset( $unused );
				$this->renderData = $data;
				return 'rendered-progress';
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
