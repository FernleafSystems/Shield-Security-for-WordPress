<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\ContainerRenderer;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\{
	Activity,
	General,
	Sessions,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestIpUtils
};

class IpAnalyseContainerRendererTest extends BaseUnitTest {

	private object $capture;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_ip' => new UnitTestIpUtils(),
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_render_passes_ip_only() :void {
		$output = ( new ContainerRenderer() )->render( '198.51.100.20' );

		$this->assertSame( 'rendered-ipanalyse-container', $output );
		$this->assertSame( Container::class, $this->capture->action );
		$this->assertSame(
			[
				'ip' => '198.51.100.20',
			],
			$this->capture->actionData
		);
	}

	public function test_container_excludes_bot_signals_tab_content() :void {
		$container = new class( [
			'ip' => '198.51.100.20',
		] ) extends Container {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}
		};

		$data = $container->exposeRenderData();

		$this->assertArrayHasKey( 'general', $data[ 'content' ] );
		$this->assertArrayHasKey( 'sessions', $data[ 'content' ] );
		$this->assertArrayHasKey( 'activity', $data[ 'content' ] );
		$this->assertArrayHasKey( 'traffic', $data[ 'content' ] );
		$this->assertArrayNotHasKey( 'signals', $data[ 'content' ] );
		$this->assertArrayNotHasKey( 'nav_signals', $data[ 'strings' ] );
		$this->assertSame(
			[
				General::class,
				Sessions::class,
				Activity::class,
				Traffic::class,
			],
			\array_column( $this->capture->renders, 'action' )
		);
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'action'     => '',
			'actionData' => [],
			'renders'    => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->action_router = new class( $this->capture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->action = $action;
				$this->capture->actionData = $actionData;
				$this->capture->renders[] = [
					'action'     => $action,
					'actionData' => $actionData,
				];
				return 'rendered-ipanalyse-container';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
