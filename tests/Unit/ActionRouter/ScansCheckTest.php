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
		$failureMessage = 'producer_failure_detail';
		$controller = $this->installController( $failureMessage );

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
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
	}

	public function test_exec_preserves_request_id_precedence_when_failed_scan_query_returns_multiple_rows() :void {
		$controller = $this->installController( '', '', [], [ 'afs' => false, 'wpv' => false, 'apc' => false ], 0.2, [
			(object)[
				'id'     => 32,
				'status' => 'failed',
				'meta'   => [
					'last_error' => 'second_requested_failure',
				],
			],
			(object)[
				'id'     => 21,
				'status' => 'failed',
				'meta'   => [
					'last_error' => 'first_requested_failure',
				],
			],
		] );

		$action = new ScansCheck( [
			'scan_ids' => [ 21, 32 ],
		] );
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$this->assertSame( 'first_requested_failure', $action->response()->payload()[ 'failure_message' ] ?? '' );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
		$this->assertSame( [ 21, 32 ], $controller->db_con->scans->selector->filteredIDs );
	}

	public function test_exec_uses_default_failed_message_when_failed_row_has_no_error_meta() :void {
		$controller = $this->installController( '', '', [], [ 'afs' => false, 'wpv' => false, 'apc' => false ], 0.2, [
			(object)[
				'id'     => 21,
				'status' => 'failed',
				'meta'   => [],
			],
		] );

		$action = new ScansCheck( [
			'scan_ids' => [ 21 ],
		] );
		$method = new \ReflectionMethod( ScansCheck::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$this->assertArrayHasKey( 'failure_message', $action->response()->payload() );
		$this->assertSame( 1, $controller->db_con->scans->selector->queryCount );
	}

	public function test_exec_reports_running_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			'',
			'wpv',
			[ 'wpv' ],
			[ 'afs' => false, 'wpv' => true, 'apc' => false ],
			0.42
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
		$this->assertSame( [ 'wpv' ], $controller->comps->scans_queue->receivedEnqueued );
		$this->assertSame( ScansProgress::class, $controller->action_router->renderClass );
		$this->assertSame( ScansCheck::SCAN_MODAL_STATE_RUNNING, $controller->action_router->renderData[ 'modal_state' ] ?? '' );
		$this->assertModalRenderInputDoesNotCarryDerivedFlags( $controller->action_router->renderData );
		$this->assertSame( 42, $controller->action_router->renderData[ 'progress' ] ?? null );
		$this->assertArrayHasKey( 'current_scan', $controller->action_router->renderData );
	}

	public function test_exec_reports_completed_scan_modal_state_and_render_input() :void {
		$controller = $this->installController(
			'',
			'',
			[],
			[ 'afs' => false, 'wpv' => false, 'apc' => false ],
			0.25
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
		$this->assertSame( [], $controller->comps->scans_queue->receivedEnqueued );
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
		float $progress = 0.2,
		array $failedScanRows = []
	) :Controller {
		ServicesState::installItems( [
			'service_wpdb' => new class( $currentScan, $enqueued ) extends Db {
				private string $currentScan;
				private array $enqueued;

				public function __construct( string $currentScan, array $enqueued ) {
					$this->currentScan = $currentScan;
					$this->enqueued = $enqueued;
				}

				public function selectCustom( $query, $format = null ) {
					unset( $query, $format );
					$ordered = $this->currentScan === '' ? [] : [ $this->currentScan ];
					foreach ( $this->enqueued as $scan ) {
						if ( !\in_array( $scan, $ordered, true ) ) {
							$ordered[] = $scan;
						}
					}
					return \array_map(
						static fn( string $scan ) :array => [
							'scan'       => $scan,
							'status'     => 'running',
							'created_at' => 1,
						],
						$ordered
					);
				}
			},
		] );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'scans' => new class( $failureMessage, $failedScanRows ) {
				public object $selector;

				private string $failureMessage;
				private array $failedScanRows;

				public function __construct( string $failureMessage, array $failedScanRows ) {
					$this->failureMessage = $failureMessage;
					$this->failedScanRows = $failedScanRows;
					$this->selector = new class( $failureMessage, $failedScanRows ) {
						public int $queryCount = 0;
						public array $filteredIDs = [];
						private array $ids = [];

						private string $failureMessage;
						private array $failedScanRows;

						public function __construct( string $failureMessage, array $failedScanRows ) {
							$this->failureMessage = $failureMessage;
							$this->failedScanRows = $failedScanRows;
						}

						public function filterByIDs( array $ids ) :self {
							$this->ids = $ids;
							$this->filteredIDs = $ids;
							return $this;
						}

						public function filterByStatus( string $status ) :self {
							unset( $status );
							return $this;
						}

						public function queryWithResult() :array {
							$this->queryCount++;
							if ( empty( $this->ids ) ) {
								return [];
							}
							if ( !empty( $this->failedScanRows ) ) {
								return \array_values( \array_filter(
									$this->failedScanRows,
									fn( object $row ) :bool => \in_array( (int)$row->id, $this->ids, true )
								) );
							}
							if ( $this->failureMessage === '' ) {
								return [];
							}
							return [
								(object)[
									'id'     => $this->ids[ 0 ],
									'status' => 'failed',
									'meta'   => [
										'last_error' => $this->failureMessage,
									],
								],
							];
						}
					};
				}

				public function getTable() :string {
					return 'shield_scans';
				}

				public function getQuerySelector() :object {
					return $this->selector;
				}
			},
		];
		$controller->comps = (object)[
			'scans' => new class {
				public function getScanCon( string $slug ) :object {
					return new class( $slug ) {
						private string $slug;

						public function __construct( string $slug ) {
							$this->slug = $slug;
						}

						public function getScanName() :string {
							return 'Scan Name: '.$this->slug;
						}
					};
				}
			},
			'scans_queue' => new class( $runningStates, $progress ) {
				public array $receivedEnqueued = [];

				private array $runningStates;
				private float $progress;

				public function __construct( array $runningStates, float $progress ) {
					$this->runningStates = $runningStates;
					$this->progress = $progress;
				}

				public function getScansRunningStates( ?array $enqueued = null ) :array {
					$this->receivedEnqueued = $enqueued ?? [];
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
