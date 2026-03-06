<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\ContainerRenderer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class IpAnalyseContainerRendererTest extends BaseUnitTest {

	private object $capture;

	protected function setUp() :void {
		parent::setUp();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_passes_ip_and_true_inline_tabs_flag() :void {
		$output = ( new ContainerRenderer() )->render( '203.0.113.88', true );

		$this->assertSame( 'rendered-ipanalyse-container', $output );
		$this->assertSame( Container::class, $this->capture->action );
		$this->assertSame(
			[
				'ip'                 => '203.0.113.88',
				'render_inline_tabs' => true,
			],
			$this->capture->actionData
		);
	}

	public function test_render_passes_ip_and_false_inline_tabs_flag() :void {
		$output = ( new ContainerRenderer() )->render( '198.51.100.20', false );

		$this->assertSame( 'rendered-ipanalyse-container', $output );
		$this->assertSame( Container::class, $this->capture->action );
		$this->assertSame(
			[
				'ip'                 => '198.51.100.20',
				'render_inline_tabs' => false,
			],
			$this->capture->actionData
		);
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'action'     => '',
			'actionData' => [],
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
				return 'rendered-ipanalyse-container';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
