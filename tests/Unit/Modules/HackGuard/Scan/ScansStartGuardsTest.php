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

	public function test_start_blocked_message_prefers_findings_reconcile_state() :void {
		$this->installController( 'reconciling', true );

		$scans = new ScansController();

		$this->assertSame(
			'Scan findings are temporarily unavailable while the findings model is being upgraded.',
			$scans->getStartBlockedMessage()
		);
	}

	public function test_cli_is_exempt_from_loopback_guard_but_web_is_not() :void {
		$this->installController( 'ready', false );

		$scans = new ScansController();

		$this->assertSame(
			"Scans can't start because this site currently can't make HTTP requests to itself.",
			$scans->getStartBlockedMessage()
		);
		$this->assertSame( '', $scans->getStartBlockedMessage( true ) );
	}

	public function test_action_router_start_returns_maintenance_message_instead_of_generic_no_selection() :void {
		$request = new UnitTestRequest();
		$request->post = [];
		ServicesState::installItems( [
			'service_request' => $request,
		] );

		$this->installActionController();

		$action = new ScansStart();
		$method = new \ReflectionMethod( ScansStart::class, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame(
			'Scan findings are temporarily unavailable while the findings model is being upgraded.',
			(string)( $payload[ 'message' ] ?? '' )
		);
	}

	private function installController( string $findingsState, bool $canLoopback ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->opts = new class( $findingsState ) {
			private string $findingsState;

			public function __construct( string $findingsState ) {
				$this->findingsState = $findingsState;
			}

			public function optGet( string $key ) {
				return $key === 'scan_findings_model_state' ? $this->findingsState : null;
			}
		};
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

	private function installActionController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'scans' => new class {
				public function getStartBlockedMessage( bool $isCli = false ) :string {
					unset( $isCli );
					return 'Scan findings are temporarily unavailable while the findings model is being upgraded.';
				}

				public function canStartScans( bool $isCli = false ) :bool {
					unset( $isCli );
					return false;
				}

				public function getScanSlugs() :array {
					return [ 'afs', 'apc', 'wpv' ];
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
