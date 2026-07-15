<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan\Queue\Support\ScanQueueLifecycleHarness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};

class BuildScanRuntimeTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_maps_running_scan_runtime_from_live_dependencies() :void {
		$scanCalls = (object)[ 'requested_slugs' => [], 'received_enqueued' => [] ];
		$this->installRuntimeEnvironment(
			[
				[ 'scan' => 'afs', 'status' => 'running', 'created_at' => 20 ],
				[ 'scan' => 'wpv', 'status' => 'queued', 'created_at' => 10 ],
			],
			[ 'afs' => true, 'wpv' => true, 'apc' => false ],
			'File Scanner',
			0.42,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertTrue( $runtime[ 'is_running' ] );
		$this->assertSame( 2, $runtime[ 'enqueued_count' ] );
		$this->assertSame( [ 'afs' => true, 'wpv' => true, 'apc' => false ], $runtime[ 'running_states' ] );
		$this->assertSame( 'afs', $runtime[ 'current_slug' ] );
		$this->assertSame( 'File Scanner', $runtime[ 'current_name' ] );
		$this->assertSame( 0.42, $runtime[ 'progress' ] );
		$this->assertSame( [ 'afs' ], $scanCalls->requested_slugs );
		$this->assertSame( [ 'afs', 'wpv' ], $scanCalls->received_enqueued );
	}

	public function test_build_maps_single_running_scan_runtime() :void {
		$scanCalls = (object)[ 'requested_slugs' => [], 'received_enqueued' => [] ];
		$this->installRuntimeEnvironment(
			[
				[ 'scan' => 'wpv', 'status' => 'queued', 'created_at' => 10 ],
			],
			[ 'afs' => false, 'wpv' => true, 'apc' => false ],
			'Vulnerability Scan',
			1.0,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertTrue( $runtime[ 'is_running' ] );
		$this->assertSame( 1, $runtime[ 'enqueued_count' ] );
		$this->assertSame( [ 'afs' => false, 'wpv' => true, 'apc' => false ], $runtime[ 'running_states' ] );
		$this->assertSame( 'wpv', $runtime[ 'current_slug' ] );
		$this->assertSame( 'Vulnerability Scan', $runtime[ 'current_name' ] );
		$this->assertSame( 1.0, $runtime[ 'progress' ] );
		$this->assertSame( [ 'wpv' ], $scanCalls->requested_slugs );
		$this->assertSame( [ 'wpv' ], $scanCalls->received_enqueued );
	}

	public function test_build_reports_not_running_when_no_enqueued_scans_exist() :void {
		$scanCalls = (object)[ 'requested_slugs' => [], 'received_enqueued' => [] ];
		$this->installRuntimeEnvironment(
			[],
			[ 'afs' => false, 'wpv' => false, 'apc' => false ],
			'Unused Scan Name',
			1.0,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertFalse( $runtime[ 'is_running' ] );
		$this->assertSame( 0, $runtime[ 'enqueued_count' ] );
		$this->assertSame( '', $runtime[ 'current_slug' ] );
		$this->assertSame( '', $runtime[ 'current_name' ] );
		$this->assertSame( [], $scanCalls->requested_slugs );
		$this->assertSame( [], $scanCalls->received_enqueued );
	}

	public function test_build_reports_failed_or_completed_scan_snapshot_as_not_running() :void {
		$scanCalls = (object)[ 'requested_slugs' => [], 'received_enqueued' => [] ];
		$this->installRuntimeEnvironment(
			[
				[ 'scan' => 'afs', 'status' => 'failed', 'created_at' => 10 ],
				[ 'scan' => 'wpv', 'status' => 'running', 'finished_at' => 20, 'created_at' => 20 ],
			],
			[ 'afs' => false, 'wpv' => false, 'apc' => false ],
			'Failed Scan',
			0.0,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertFalse( $runtime[ 'is_running' ] );
		$this->assertSame( 0, $runtime[ 'enqueued_count' ] );
		$this->assertSame( '', $runtime[ 'current_slug' ] );
		$this->assertSame( '', $runtime[ 'current_name' ] );
		$this->assertSame( [], $scanCalls->requested_slugs );
		$this->assertSame( [], $scanCalls->received_enqueued );
	}

	public function test_build_keeps_stale_active_scan_visible_for_admin_polling_recovery() :void {
		$scanCalls = (object)[ 'requested_slugs' => [], 'received_enqueued' => [] ];
		$this->installRuntimeEnvironment(
			[
				[ 'scan' => 'afs', 'status' => 'running', 'created_at' => 10, 'last_process_at' => 1 ],
			],
			[ 'afs' => false, 'wpv' => false, 'apc' => false ],
			'File Scanner',
			0.0,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertTrue( $runtime[ 'is_running' ] );
		$this->assertSame( 1, $runtime[ 'enqueued_count' ] );
		$this->assertSame( 'afs', $runtime[ 'current_slug' ] );
		$this->assertSame( 'File Scanner', $runtime[ 'current_name' ] );
		$this->assertSame( [ 'afs' ], $scanCalls->received_enqueued );
		$this->assertSame( [ 'afs' ], $scanCalls->requested_slugs );
	}

	private function installRuntimeEnvironment(
		array $scanRows,
		array $runningStates,
		string $currentName,
		float $progress,
		object $scanCalls
	) :void {
		$harness = ( new ScanQueueLifecycleHarness() )->install();
		foreach ( $scanRows as $scanRow ) {
			$harness->insertScan( $scanRow );
		}

		$harness->controller->comps->scans_queue = new class( $runningStates, $progress, $scanCalls ) {
			private array $runningStates;
			private float $progress;
			private object $scanCalls;

			public function __construct( array $runningStates, float $progress, object $scanCalls ) {
				$this->runningStates = $runningStates;
				$this->progress = $progress;
				$this->scanCalls = $scanCalls;
			}

			public function getScansRunningStates( ?array $enqueued = null ) :array {
				$this->scanCalls->received_enqueued = $enqueued ?? [];
				return $this->runningStates;
			}

			public function getScanJobProgress() :float {
				return $this->progress;
			}
		};
		$harness->controller->comps->scans = new class( $currentName, $scanCalls ) {
			private string $currentName;
			private object $scanCalls;

			public function __construct( string $currentName, object $scanCalls ) {
				$this->currentName = $currentName;
				$this->scanCalls = $scanCalls;
			}

			public function getScanCon( string $slug ) :object {
				$this->scanCalls->requested_slugs[] = $slug;
				return new class( $this->currentName ) {
					private string $currentName;

					public function __construct( string $currentName ) {
						$this->currentName = $currentName;
					}

					public function getScanName() :string {
						return $this->currentName;
					}
				};
			}
		};
	}
}
