<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageDashboardOverview,
	PageOperatorModeLanding
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageDashboardOverviewBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_dashboard_overview_renders_operator_mode_landing_content_only() :void {
		$page = new PageDashboardOverview();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( PageOperatorModeLanding::class, (string)$this->renderCapture->action );
		$this->assertSame( [], (array)$this->renderCapture->actionData );
		$this->assertSame(
			[ 'operator_mode_landing' => 'rendered-operator-mode-landing' ],
			$renderData[ 'content' ] ?? []
		);
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'action'     => '',
			'actionData' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->action = $action;
				$this->capture->actionData = $actionData;
				return 'rendered-operator-mode-landing';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
