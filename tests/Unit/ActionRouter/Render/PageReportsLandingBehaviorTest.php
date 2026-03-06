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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageReportsLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

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

	public function test_landing_vars_include_two_tiles_and_inline_settings_panel() :void {
		$page = new PageReportsLanding();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$tiles = $vars[ 'report_tiles' ] ?? [];

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

		$this->assertCount( 2, $tiles );
		$this->assertSame(
			[
				PluginNavs::SUBNAV_REPORTS_LIST,
				PluginNavs::SUBNAV_REPORTS_SETTINGS,
			],
			\array_column( $tiles, 'key' )
		);
		$this->assertSame( 'reports_table', $tiles[ 0 ][ 'panel_variant' ] ?? '' );
		$this->assertSame( 'rendered-1', $tiles[ 0 ][ 'panel_content' ] ?? '' );
		$this->assertSame( 'config_form', $tiles[ 1 ][ 'panel_variant' ] ?? '' );
		$this->assertSame( 'rendered-2', $tiles[ 1 ][ 'panel_content' ] ?? '' );
	}

	public function test_landing_hrefs_include_canonical_and_legacy_workspace_routes() :void {
		$page = new PageReportsLanding();
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'reports_list'     => '/admin/reports/list',
				'reports_alerts'   => '/admin/reports/alerts',
				'reports_reporting' => '/admin/reports/reporting',
				'reports_charts'   => '/admin/reports/charts',
				'reports_settings' => '/admin/reports/settings',
			],
			$hrefs
		);
	}

	public function test_landing_panel_defaults_to_security_reports_subnav() :void {
		$page = new PageReportsLanding();
		$panel = $this->invokeNonPublicMethod( $page, 'getLandingPanel' );

		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, $panel[ 'active_target' ] ?? '' );
	}

	public function test_landing_strings_include_hint_message() :void {
		$page = new PageReportsLanding();
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );
		$this->assertSame( 'Select a reports area above to view details.', $strings[ 'landing_hint' ] ?? '' );
	}

	public function test_mode_shell_contract_is_exposed_in_render_data() :void {
		$page = new PageReportsLanding();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 'reports', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'warning', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertSame( 'compact', $renderData[ 'vars' ][ 'mode_shell' ][ 'header_density' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? false ) );
		$this->assertCount( 2, $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
		$this->assertSame( PluginNavs::SUBNAV_REPORTS_LIST, $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_panel' ][ 'is_open' ] ?? false ) );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
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
		];
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
