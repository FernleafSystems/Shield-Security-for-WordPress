<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\{
	ChartsTrends,
	ReportsTable
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReportsLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Request;

class PageReportsLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [ 'service_request' => new Request() ] );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_tabs_open_with_charts_and_keep_their_workspace_links() :void {
		$page = new PageReportsLandingUnitTestDouble();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( PluginNavs::SUBNAV_REPORTS_CHARTS, $vars[ 'reports_current_tab' ] ?? '' );
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_CHARTS,
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_column( $vars[ 'reports_tabs' ][ 'tabs' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ '/reports/charts', '/reports/list', '/reports/settings' ],
			\array_column( $vars[ 'reports_tabs' ][ 'tabs' ] ?? [], 'href' )
		);
		$this->assertSame( [ true, false, false ], \array_column( $vars[ 'reports_tabs' ][ 'tabs' ] ?? [], 'is_current' ) );
	}

	public function test_a_workspace_link_opens_its_tab() :void {
		$page = new PageReportsLandingUnitTestDouble();
		$page->action_data = [ 'workspace' => PluginNavs::SUBNAV_REPORTS_SETTINGS ];
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( PluginNavs::SUBNAV_REPORTS_SETTINGS, $vars[ 'reports_current_tab' ] ?? '' );
		$this->assertSame( [ false, false, true ], \array_column( $vars[ 'reports_tabs' ][ 'tabs' ] ?? [], 'is_current' ) );
	}

	public function test_every_tab_is_on_the_page_with_the_settings_from_configure() :void {
		$page = new PageReportsLandingUnitTestDouble();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame(
			[
				[ 'key' => PluginNavs::SUBNAV_REPORTS_CHARTS, 'body' => 'rendered-1' ],
				[ 'key' => PluginNavs::SUBNAV_REPORTS_LIST, 'body' => 'rendered-2' ],
				[ 'key' => PluginNavs::SUBNAV_REPORTS_SETTINGS, 'body' => 'SETTINGS_HTML' ],
			],
			$vars[ 'reports_panels' ] ?? []
		);
		$this->assertSame( [ ChartsTrends::class, ReportsTable::class ], \array_column( $this->renderCapture->calls, 'action' ) );
		$this->assertSame( 'alerts', PageReportsLanding::SETTINGS_GOAL );
	}

	public function test_render_data_uses_the_mode_shell_without_a_drill_shell() :void {
		$page = new PageReportsLandingUnitTestDouble();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];

		$this->assertSame( 'reports', $vars[ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( '/admin/home', $vars[ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertTrue( (bool)( $vars[ 'mode_shell' ][ 'use_operator_chrome' ] ?? false ) );
		$this->assertSame( 'reports', $vars[ 'mode_shell' ][ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertArrayNotHasKey( 'drill_shell', $vars );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls'          => [],
			'template_calls' => [],
		];
		$renderStub = new class( $this->renderCapture ) {
			private object $capture;

			private string $template = '';

			private array $vars = [];

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function setTemplate( string $template ) :self {
				$this->template = $template;
				return $this;
			}

			public function forceTemplate( string $template ) :void {
				$this->template = $template;
			}

			public function setData( array $vars ) :self {
				$this->vars = $vars;
				return $this;
			}

			public function render() :string {
				$this->capture->template_calls[] = [
					'template' => $this->template,
					'vars'     => $this->vars,
				];
				return 'rendered-template:'.$this->template;
			}
		};

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminHome() :string {
				return '/admin/home';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function reportsHome( string $workspace = '' ) :string {
				return '/reports/'.$workspace;
			}
		};
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->calls[] = [
					'action'     => $action,
					'action_data' => $actionData,
				];
				return 'rendered-'.\count( $this->capture->calls );
			}
		};
		$controller->comps = (object)[
			'zones' => new class {
				public function getZoneComponent( string $slug ) :object {
					return new class( $slug ) {
						private string $slug;

						public function __construct( string $slug ) {
							$this->slug = $slug;
						}

						public function getOptions() :array {
							return [
								$this->slug.'-opt-a',
								$this->slug.'-opt-b',
							];
						}
					};
				}
			},
			'render' => $renderStub,
		];
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

class PageReportsLandingUnitTestDouble extends PageReportsLanding {

	protected function renderSettings() :string {
		return 'SETTINGS_HTML';
	}
}
