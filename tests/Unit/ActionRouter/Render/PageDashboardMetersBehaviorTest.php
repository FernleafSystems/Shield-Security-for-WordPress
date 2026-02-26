<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageDashboardMeters;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component\Base as MeterComponent;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageDashboardMetersBehaviorTest extends BaseUnitTest {

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

	public function test_dashboard_meters_renders_progress_meters_with_config_channel() :void {
		$page = new PageDashboardMetersUnitTestDouble( [
			'is_show_view_as' => false,
			'view_as_msg'     => '',
			'view_as_href'    => [],
		] );
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( ProgressMeters::class, (string)$this->renderCapture->action );
		$this->assertSame(
			[ 'meter_channel' => MeterComponent::CHANNEL_CONFIG ],
			(array)$this->renderCapture->actionData
		);
		$this->assertArrayHasKey( 'content', $renderData );
		$this->assertArrayHasKey( 'flags', $renderData );
		$this->assertArrayHasKey( 'imgs', $renderData );
		$this->assertArrayHasKey( 'strings', $renderData );
		$this->assertSame( 'rendered-progress-meters', (string)( $renderData[ 'content' ][ 'progress_meters' ] ?? '' ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'is_show_view_as_message' ] ?? true ) );
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
				return 'rendered-progress-meters';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

class PageDashboardMetersUnitTestDouble extends PageDashboardMeters {

	private array $viewAsState;

	public function __construct( array $viewAsState ) {
		$this->viewAsState = $viewAsState;
	}

	protected function getViewAsState() :array {
		return $this->viewAsState;
	}
}
