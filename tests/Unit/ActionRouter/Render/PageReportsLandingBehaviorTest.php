<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsTable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReportsLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use Twig\{
	Environment,
	Loader\FilesystemLoader
};

class PageReportsLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;
	use PluginPathsTrait;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_workspace_contracts_include_two_cards_and_inline_settings_panel() :void {
		$page = new PageReportsLanding();
		$cards = $this->invokeNonPublicMethod( $page, 'getWorkspaceCards' );
		$panels = $this->invokeNonPublicMethod( $page, 'getWorkspacePanels' );

		$this->assertSame(
			ReportsTable::class,
			$this->renderCapture->calls[ 0 ][ 'action' ] ?? ''
		);
		$this->assertSame( [], $this->renderCapture->calls[ 0 ][ 'action_data' ] ?? null );
		$this->assertSame( OptionsFormFor::class, $this->renderCapture->calls[ 1 ][ 'action' ] ?? '' );
		$this->assertCount( 4, $this->renderCapture->calls[ 1 ][ 'action_data' ][ 'options' ] ?? [] );
		$this->assertSame(
			[ 'instant_alerts-opt-a', 'reporting-opt-a' ],
			[
				$this->renderCapture->calls[ 1 ][ 'action_data' ][ 'options' ][ 0 ] ?? '',
				$this->renderCapture->calls[ 1 ][ 'action_data' ][ 'options' ][ 2 ] ?? '',
			]
		);

		$this->assertCount( 2, $cards );
		$this->assertCount( 2, $panels );
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_column( $cards, 'key' )
		);
		$this->assertSame( 'button', $cards[ 0 ][ 'tile' ][ 'tag' ] ?? '' );
		$this->assertSame( 'workspace', $cards[ 0 ][ 'tile' ][ 'data_drill_target' ] ?? '' );
		$this->assertSame( '', $cards[ 0 ][ 'tile' ][ 'data_drill_zone_selection' ] ?? null );
		$this->assertSame( '', $cards[ 0 ][ 'tile' ][ 'data_drill_bucket_selection' ] ?? null );
		$this->assertSame( '', $cards[ 0 ][ 'tile' ][ 'data_drill_group_selection' ] ?? null );
		$this->assertSame( 'rendered-1', $panels[ 0 ][ 'body' ] ?? '' );
		$this->assertSame( 'rendered-2', $panels[ 1 ][ 'body' ] ?? '' );
		$this->assertTrue( (bool)( $panels[ 0 ][ 'is_default' ] ?? false ) );
		$this->assertFalse( (bool)( $panels[ 1 ][ 'is_default' ] ?? true ) );
		$this->assertNotSame( '', $panels[ 0 ][ 'data_reports_workspace_selection' ] ?? '' );
		$this->assertSame(
			'Manage instant alerts and report delivery settings together.',
			$panels[ 1 ][ 'description' ] ?? ''
		);

		$listSelection = \json_decode(
			(string)( $cards[ 0 ][ 'tile' ][ 'data_reports_workspace_selection' ] ?? '' ),
			true
		);
		$this->assertIsArray( $listSelection );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, $listSelection[ 'key' ] ?? '' );
		$this->assertSame( 'Security Reports', $listSelection[ 'label' ] ?? '' );
		$this->assertSame( 'Security Reports', $listSelection[ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'reports', $listSelection[ 'header' ][ 'color_key' ] ?? '' );
	}

	public function test_workspace_cards_render_through_strict_twig_layer_template() :void {
		$page = new PageReportsLanding();
		$html = $this->twig()->render( '/wpadmin/components/reports/layer_workspaces.twig', [
			'workspaces' => $this->invokeNonPublicMethod( $page, 'getWorkspaceCards' ),
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			2,
			$xpath->query( '//button[@data-drill-target="workspace" and @data-reports-workspace-selection]' )->length,
			'Reports workspaces should render as shared drill-down buttons with explicit workspace selection payloads'
		);
		$this->assertSame(
			1,
			$xpath->query( '//button[.//h4[normalize-space()="Security Reports"]]' )->length,
			'The Security Reports workspace card should render through the strict Twig layer template'
		);
		$this->assertSame(
			1,
			$xpath->query( '//button[.//h4[normalize-space()="Reporting & Alerts Configuration"]]' )->length,
			'The Reporting & Alerts Configuration workspace card should render through the strict Twig layer template'
		);
	}

	public function test_render_workspaces_layer_is_not_clobbered_by_nested_action_renders() :void {
		$this->installControllerStub( true );

		$page = new PageReportsLanding();
		$output = $this->invokeNonPublicMethod( $page, 'renderWorkspacesLayer' );

		$this->assertSame( 'rendered-template:/wpadmin/components/reports/layer_workspaces.twig', $output );
		$this->assertSame(
			'/wpadmin/components/reports/layer_workspaces.twig',
			$this->renderCapture->template_calls[ 0 ][ 'template' ] ?? ''
		);
	}

	public function test_render_data_exposes_drill_shell_and_clears_legacy_mode_panel_contract() :void {
		$page = new PageReportsLanding();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];

		$this->assertSame( 'reports', $vars[ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'warning', $vars[ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertSame( 'compact', $vars[ 'mode_shell' ][ 'header_density' ] ?? '' );
		$this->assertSame( '/admin/home', $vars[ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertTrue( (bool)( $vars[ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertFalse( (bool)( $vars[ 'mode_shell' ][ 'is_interactive' ] ?? true ) );
		$this->assertTrue( (bool)( $vars[ 'mode_shell' ][ 'use_operator_chrome' ] ?? false ) );
		$this->assertSame( 'Reports', $vars[ 'mode_shell' ][ 'root_step' ][ 'title' ] ?? '' );
		$this->assertSame( 'reports', $vars[ 'mode_shell' ][ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $vars[ 'mode_tiles' ] ?? [ 'unexpected' ] );
		$this->assertSame( '', $vars[ 'mode_panel' ][ 'active_target' ] ?? 'unexpected' );
		$this->assertFalse( (bool)( $vars[ 'mode_panel' ][ 'is_open' ] ?? true ) );
		$this->assertSame( 'reports_drill_shell', $vars[ 'drill_shell' ][ 'id' ] ?? '' );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] ?? -1 );
		$this->assertSame( [ 'workspaces', 'workspace' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ] ?? [], 'key' ) );
		$this->assertSame( 'rendered-template:/wpadmin/components/reports/layer_workspaces.twig', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'body' ] ?? '' );
		$this->assertSame( 'rendered-template:/wpadmin/components/reports/layer_workspace.twig', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'body' ] ?? '' );
		$this->assertSame( 'Workspace', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] ?? '' );
		$this->assertSame( 'Select', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'badge' ] ?? '' );
		$this->assertSame(
			[
				'/wpadmin/components/reports/layer_workspaces.twig',
				'/wpadmin/components/reports/layer_workspace.twig',
			],
			\array_column( $this->renderCapture->template_calls ?? [], 'template' )
		);
	}

	private function twig() :Environment {
		return new Environment(
			new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => true,
			]
		);
	}

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}

	private function installControllerStub( bool $simulateRenderStateLeak = false ) :void {
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
		};
		$controller->action_router = new class( $this->renderCapture, $renderStub, $simulateRenderStateLeak ) {
			private object $capture;
			private object $renderStub;
			private bool $simulateRenderStateLeak;

			public function __construct( object $capture, object $renderStub, bool $simulateRenderStateLeak ) {
				$this->capture = $capture;
				$this->renderStub = $renderStub;
				$this->simulateRenderStateLeak = $simulateRenderStateLeak;
			}

			public function render( string $action, array $actionData = [] ) :string {
				if ( $this->simulateRenderStateLeak ) {
					$this->renderStub->forceTemplate(
						$action === ReportsTable::class
							? '/wpadmin/components/reports/table_reports.twig'
							: '/components/config/options_form_for.twig'
					);
				}
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
