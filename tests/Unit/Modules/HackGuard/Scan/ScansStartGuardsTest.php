<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\HackGuard\Scan;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansStart;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestRequest
};

class ScansStartGuardsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_cli_is_exempt_from_loopback_guard_but_web_is_not() :void {
		$this->installController( false );

		$scans = new ScansController();

		$this->assertNotSame( '', $scans->getStartBlockedMessage() );
		$this->assertSame( '', $scans->getStartBlockedMessage( true ) );
	}

	public function test_action_router_start_returns_maintenance_message_instead_of_generic_no_selection() :void {
		$request = new UnitTestRequest();
		$request->post = [];
		ServicesState::installItems( [
			'service_request' => $request,
		] );

		$this->installActionController(
			canStart: false,
			blockedReasons: [ 'reason_not_call_self' ],
			startResult: StartScansResult::fromRequested( [] )
		);

		$action = new ScansStart();
		$method = new \ReflectionMethod( ScansStart::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( StartScansResult::CODE_START_BLOCKED, $payload[ 'error_code' ] ?? '' );
		$this->assertSame( [ 'reason_not_call_self' ], $payload[ 'blocked_reasons' ] ?? [] );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function test_action_router_start_returns_structured_failure_for_selected_scan_that_cannot_start() :void {
		$request = new UnitTestRequest();
		$request->post = [ 'afs' => 'Y' ];
		ServicesState::installItems( [
			'service_request' => $request,
		] );

		$this->installActionController(
			canStart: true,
			blockedReasons: [],
			startResult: StartScansResult::fromRequested( [ 'afs' ] )
										->addFailure( 'afs', StartScansResult::REASON_ALREADY_EXISTS )
		);

		$action = new ScansStart();
		$method = new \ReflectionMethod( ScansStart::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( StartScansResult::CODE_START_FAILED, $payload[ 'error_code' ] ?? '' );
		$this->assertSame( [ StartScansResult::REASON_ALREADY_EXISTS ], \array_column( $payload[ 'start_failures' ] ?? [], 'reason' ) );
	}

	public function test_action_router_start_allows_partial_success_with_started_ids_and_failures() :void {
		$request = new UnitTestRequest();
		$request->post = [ 'afs' => 'Y', 'wpv' => 'Y' ];
		ServicesState::installItems( [
			'service_request' => $request,
		] );

		$this->installActionController(
			canStart: true,
			blockedReasons: [],
			startResult: StartScansResult::fromRequested( [ 'afs', 'wpv' ] )
										->addStarted( 'afs', 31 )
										->addFailure( 'wpv', StartScansResult::REASON_CREATE_FAILED )
		);

		$action = new ScansStart();
		$method = new \ReflectionMethod( ScansStart::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( [ 31 ], $payload[ 'scan_ids' ] ?? [] );
		$this->assertSame( StartScansResult::CODE_PARTIAL_START, $payload[ 'error_code' ] ?? '' );
		$this->assertSame( [ StartScansResult::REASON_CREATE_FAILED ], \array_column( $payload[ 'start_failures' ] ?? [], 'reason' ) );
	}

	private function installController( bool $canLoopback ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin = new class( $canLoopback ) extends ModCon {
			private bool $canLoopback;

			public function __construct( bool $canLoopback ) {
				$this->canLoopback = $canLoopback;
			}

			public function canSiteLoopback() :bool {
				return $this->canLoopback;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function installActionController(
		bool $canStart,
		array $blockedReasons,
		StartScansResult $startResult
	) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans' => new class( $canStart, $blockedReasons, $startResult ) {
				public function __construct(
					private bool $canStart,
					private array $blockedReasons,
					private StartScansResult $startResult
				) {
				}

				public function getStartBlockedMessage( bool $isCli = false ) :string {
					unset( $isCli );
					return $this->canStart ? '' : 'blocked';
				}

				public function canStartScans( bool $isCli = false ) :bool {
					unset( $isCli );
					return $this->canStart;
				}

				public function getReasonsScansCantExecute() :array {
					return $this->blockedReasons;
				}

				public function getScanSlugs() :array {
					return [ 'afs', 'apc', 'wpv' ];
				}

				public function startNewScans( array $scans, bool $resetIgnored = false ) :StartScansResult {
					unset( $scans, $resetIgnored );
					return $this->startResult;
				}
			},
			'scans_queue' => new class {
				public function hasRunningScans() :bool {
					return false;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
