<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

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
		$scanCalls = (object)[
			'requested_slugs' => [],
		];
		$this->installRuntimeEnvironment(
			'afs',
			[ 'afs', 'wpv' ],
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
	}

	public function test_build_leaves_current_scan_name_empty_when_nothing_is_running() :void {
		$scanCalls = (object)[
			'requested_slugs' => [],
		];
		$this->installRuntimeEnvironment(
			'',
			[ 'wpv' ],
			[ 'afs' => false, 'wpv' => true, 'apc' => false ],
			'Unused Scan Name',
			1.0,
			$scanCalls
		);

		$runtime = ( new BuildScanRuntime() )->build();

		$this->assertFalse( $runtime[ 'is_running' ] );
		$this->assertSame( 1, $runtime[ 'enqueued_count' ] );
		$this->assertSame( [ 'afs' => false, 'wpv' => true, 'apc' => false ], $runtime[ 'running_states' ] );
		$this->assertSame( '', $runtime[ 'current_slug' ] );
		$this->assertSame( '', $runtime[ 'current_name' ] );
		$this->assertSame( 1.0, $runtime[ 'progress' ] );
		$this->assertSame( [], $scanCalls->requested_slugs );
	}

	private function installRuntimeEnvironment(
		string $currentSlug,
		array $enqueuedScans,
		array $runningStates,
		string $currentName,
		float $progress,
		object $scanCalls
	) :void {
		$selector = new class( $enqueuedScans ) {
			private array $enqueuedScans;

			public function __construct( array $enqueuedScans ) {
				$this->enqueuedScans = $enqueuedScans;
			}

			public function filterByNotFinished() :self {
				return $this;
			}

			public function addColumnToSelect( string $column ) :self {
				return $this;
			}

			public function setIsDistinct( bool $isDistinct ) :self {
				return $this;
			}

			public function queryWithResult() :array {
				return $this->enqueuedScans;
			}
		};

		$db = new class( $currentSlug ) extends Db {
			private string $currentSlug;

			public function __construct( string $currentSlug ) {
				$this->currentSlug = $currentSlug;
			}

			public function getVar( $sql ) {
				return $this->currentSlug;
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $selector ) {
				private object $selector;

				public function __construct( object $selector ) {
					$this->selector = $selector;
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return $this->selector;
				}
			},
			'scan_items' => new class {
				public function getTable() :string {
					return 'shield_scan_items';
				}
			},
		];
		$controller->comps = (object)[
			'scans_queue' => new class( $runningStates, $progress ) {
				private array $runningStates;
				private float $progress;

				public function __construct( array $runningStates, float $progress ) {
					$this->runningStates = $runningStates;
					$this->progress = $progress;
				}

				public function getScansRunningStates() :array {
					return $this->runningStates;
				}

				public function getScanJobProgress() :float {
					return $this->progress;
				}
			},
			'scans' => new class( $currentName, $scanCalls ) {
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
			},
		];

		PluginControllerInstaller::install( $controller );
		ServicesState::mergeItems( [
			'service_wpdb' => $db,
		] );
	}
}
