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
		$this->assertSame( [ 'operator_mode_landing' ], \array_keys( $renderData[ 'content' ] ?? [] ) );
		$this->assertSame( 'rendered-operator-mode-landing', $renderData[ 'content' ][ 'operator_mode_landing' ] ?? '' );
		$this->assertSame( 'bi bi-speedometer', $renderData[ 'imgs' ][ 'inner_page_title_icon' ] ?? '' );
		$this->assertSame( 'Shield Security', $renderData[ 'strings' ][ 'inner_page_title' ] ?? '' );
		$this->assertSame(
			'Your entire WordPress site security at a glance.',
			$renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? ''
		);
		$this->assertSame( '', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? 'missing' );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertSame( 'compact', $renderData[ 'vars' ][ 'mode_shell' ][ 'header_density' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? true ) );
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
