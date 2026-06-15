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
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
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

	public function test_render_defaults_to_embedded_container() :void {
		$output = ( new ContainerRenderer() )->render( '198.51.100.20' );

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

	public function test_render_can_request_standalone_inline_tabs() :void {
		( new ContainerRenderer() )->render( '198.51.100.20', true );

		$this->assertSame(
			[
				'ip'                 => '198.51.100.20',
				'render_inline_tabs' => true,
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
		$this->assertFalse( (bool)( $data[ 'flags' ][ 'render_inline_tabs' ] ?? true ) );
		$this->assertCount( 4, $data[ 'tabs' ] ?? [] );
		$this->assertSame(
			[ 'general', 'sessions', 'activity', 'traffic' ],
			\array_column( $data[ 'tabs' ] ?? [], 'content_key' )
		);
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

	public function test_container_keeps_other_tabs_when_one_child_render_fails() :void {
		$this->capture->renderErrors[ General::class ] = 'Exception during render for ipanalyse_general: "Boom"';

		$container = new class( [
			'ip' => '198.51.100.20',
		] ) extends Container {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}
		};

		$data = $container->exposeRenderData();

		$this->assertStringContainsString( 'shield-ipanalyse-section-fallback', $data[ 'content' ][ 'general' ] );
		$this->assertStringContainsString( 'Boom', $data[ 'content' ][ 'general' ] );
		$this->assertSame( 'rendered-sessions', $data[ 'content' ][ 'sessions' ] );
		$this->assertSame( 'rendered-activity', $data[ 'content' ][ 'activity' ] );
		$this->assertSame( 'rendered-traffic', $data[ 'content' ][ 'traffic' ] );
	}

	public function test_container_child_thrown_exception_gets_diagnostic_fallback() :void {
		$this->capture->throwActions[ Activity::class ] = 'Action exploded';

		$container = new class( [
			'ip' => '198.51.100.20',
		] ) extends Container {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}
		};

		$data = $container->exposeRenderData();

		$this->assertStringContainsString( 'shield-ipanalyse-section-fallback', $data[ 'content' ][ 'activity' ] );
		$this->assertStringContainsString( 'Action exploded', $data[ 'content' ][ 'activity' ] );
		$this->assertSame( 'rendered-general', $data[ 'content' ][ 'general' ] );
	}

	public function test_container_child_diagnostic_is_sanitized() :void {
		$this->capture->renderErrors[ General::class ] = '<script>alert(1)</script><b>Bad</b>';

		$container = new class( [
			'ip' => '198.51.100.20',
		] ) extends Container {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}
		};

		$data = $container->exposeRenderData();

		$this->assertStringContainsString( 'alert(1)Bad', $data[ 'content' ][ 'general' ] );
		$this->assertStringNotContainsString( '<script>', $data[ 'content' ][ 'general' ] );
		$this->assertStringNotContainsString( '<b>', $data[ 'content' ][ 'general' ] );
	}

	public function test_container_child_empty_output_gets_generic_diagnostic() :void {
		$this->capture->renderOutputs[ Traffic::class ] = '';

		$container = new class( [
			'ip' => '198.51.100.20',
		] ) extends Container {
			public function exposeRenderData() :array {
				return $this->getRenderData();
			}
		};

		$data = $container->exposeRenderData();

		$this->assertStringContainsString( 'shield-ipanalyse-section-fallback', $data[ 'content' ][ 'traffic' ] );
		$this->assertStringContainsString( 'No render output was returned.', $data[ 'content' ][ 'traffic' ] );
	}

	public function test_container_renderer_returns_fallback_when_container_render_fails() :void {
		$this->capture->renderErrors[ Container::class ] = 'Container exploded';

		$output = ( new ContainerRenderer() )->render( '198.51.100.20' );

		$this->assertStringContainsString( 'shield-ipanalyse-section-fallback', $output );
		$this->assertStringContainsString( 'Container exploded', $output );
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'action'        => '',
			'actionData'    => [],
			'renders'       => [],
			'renderErrors'  => [],
			'renderOutputs' => [
				Container::class => 'rendered-ipanalyse-container',
				General::class   => 'rendered-general',
				Sessions::class  => 'rendered-sessions',
				Activity::class  => 'rendered-activity',
				Traffic::class   => 'rendered-traffic',
			],
			'throwActions'   => [],
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
				return $this->capture->renderOutputs[ $action ] ?? 'rendered-ipanalyse-container';
			}

			public function action( string $action, array $actionData = [] ) {
				unset( $action );

				$renderAction = (string)( $actionData[ 'render_action_slug' ] ?? '' );
				$renderActionData = \is_array( $actionData[ 'render_action_data' ] ?? null )
					? $actionData[ 'render_action_data' ]
					: [];
				$this->capture->action = $renderAction;
				$this->capture->actionData = $renderActionData;
				$this->capture->renders[] = [
					'action'     => $renderAction,
					'actionData' => $renderActionData,
				];
				if ( isset( $this->capture->throwActions[ $renderAction ] ) ) {
					throw new \RuntimeException( $this->capture->throwActions[ $renderAction ] );
				}

				$output = $this->capture->renderOutputs[ $renderAction ] ?? 'rendered-ipanalyse-container';
				$error = $this->capture->renderErrors[ $renderAction ] ?? '';
				if ( $error !== '' ) {
					$output = $error;
				}

				return new class( $output, $error !== '' ) {
					private string $output;

					private bool $hasError;

					public function __construct( string $output, bool $hasError ) {
						$this->output = $output;
						$this->hasError = $hasError;
					}

					public function payload() :array {
						return [
							'render_output'     => $this->output,
							'html'              => $this->output,
							'render_error'      => $this->hasError,
							'render_error_code' => $this->hasError ? 'render_exception' : '',
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
