<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansCheck;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Db;

class ScansCheckTest extends BaseUnitTest {

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
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_exec_reports_failed_started_scan_with_failure_message() :void {
		$failureMessage = 'producer failure detail';
		$controller = $this->installController( failureMessage: $failureMessage );

		$action = new ScansCheck( [
			'scan_ids' => [ 21 ],
		] );
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertTrue( $payload[ 'failed' ] ?? false );
		$this->assertSame( $failureMessage, $payload[ 'failure_message' ] ?? '' );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_FAILED, $payload[ 'modal_state' ] ?? '' );
		$this->assertNotSame( '', (string)( $payload[ 'modal_html' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( ScansProgress::class, $controller->action_router->renderClass );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_FAILED, $controller->action_router->renderData[ 'modal_state' ] ?? '' );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertSame( 100, $controller->action_router->renderData[ 'progress' ] ?? null );
		$this->assertSame( $failureMessage, $controller->action_router->renderData[ 'remaining_scans' ] ?? '' );
	}

	public function test_exec_reports_running_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			currentScan: 'wpv',
			enqueued: [ (object)[ 'scan' => 'wpv' ] ],
			runningStates: [ 'afs' => false, 'wpv' => true, 'apc' => false ],
			progress: 0.42
		);

		$action = new ScansCheck();
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'failed' ] ?? true );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $payload[ 'modal_state' ] ?? '' );
		$this->assertNotSame( '', (string)( $payload[ 'modal_html' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( [ 'afs' => false, 'wpv' => true, 'apc' => false ], $payload[ 'running' ] ?? [] );
		$this->assertSame( ScansProgress::class, $controller->action_router->renderClass );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $controller->action_router->renderData[ 'modal_state' ] ?? '' );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertSame( 42, $controller->action_router->renderData[ 'progress' ] ?? null );
		$this->assertSame( 'Scan Name: wpv', $controller->action_router->renderData[ 'current_scan' ] ?? '' );
	}

	public function test_exec_reports_completed_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			currentScan: '',
			enqueued: [],
			runningStates: [ 'afs' => false, 'wpv' => false, 'apc' => false ],
			progress: 0.25
		);

		$action = new ScansCheck();
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'failed' ] ?? true );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_COMPLETED, $payload[ 'modal_state' ] ?? '' );
		$this->assertNotSame( '', (string)( $payload[ 'modal_html' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'vars', $payload );
		$this->assertSame( ScansProgress::class, $controller->action_router->renderClass );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_COMPLETED, $controller->action_router->renderData[ 'modal_state' ] ?? '' );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertSame( 100, $controller->action_router->renderData[ 'progress' ] ?? null );
	}

	private function assertModalRenderInputDoesNotCarryDerivedFlags( array $renderData ) :void {
		foreach ( [ 'is_initiating', 'is_running', 'is_complete', 'is_failed' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $renderData );
		}
	}

	private function installController(
		string $failureMessage = '',
		string $currentScan = '',
		array $enqueued = [],
		array $runningStates = [ 'afs' => false, 'wpv' => false, 'apc' => false ],
		float $progress = 0.2
	) :Controller {
		ServicesState::installItems( [
			'service_wpdb' => new class( $currentScan ) extends Db {
				public function __construct( private string $currentScan ) {
				}

				public function getVar( $sql ) {
					unset( $sql );
					return $this->currentScan;
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $failureMessage, $enqueued ) {
				public function __construct( private string $failureMessage, private array $enqueued ) {
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return new class( $this->failureMessage, $this->enqueued ) {
						public function __construct( private string $failureMessage, private array $enqueued ) {
						}

						public function byId( int $scanID ) {
							return $this->failureMessage === '' ? null : (object)[
								'id' => $scanID,
								'status' => 'failed',
								'meta' => [
									'last_error' => $this->failureMessage,
								],
							];
						}

						public function filterByNotFinished() :self {
							return $this;
						}

						public function addWhereIn( string $column, array $values ) :self {
							unset( $column, $values );
							return $this;
						}

						public function addColumnToSelect( string $column ) :self {
							unset( $column );
							return $this;
						}

						public function setIsDistinct( bool $isDistinct ) :self {
							unset( $isDistinct );
							return $this;
						}

						public function queryWithResult() :array {
							return $this->enqueued;
						}
					};
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) :object {
					return new class( $slug ) {
						public function __construct( private string $slug ) {
						}

						public function getScanName() :string {
							return 'Scan Name: '.$this->slug;
						}
					};
				}
			},
			'scans_queue' => new class( $runningStates, $progress ) {
				public function __construct( private array $runningStates, private float $progress ) {
				}

				public function getScansRunningStates() :array {
					return $this->runningStates;
				}

				public function getScanJobProgress() :float {
					return $this->progress;
				}
			},
		];
		$controller->action_router = new class {
			public string $renderClass = '';
			public array $renderData = [];

			public function render( string $renderClass, array $data ) :string {
				$this->renderClass = $renderClass;
				$this->renderData = $data;
				return 'rendered-modal';
			}
		};

		PluginControllerInstaller::install( $controller );
		return $controller;
	}
}
