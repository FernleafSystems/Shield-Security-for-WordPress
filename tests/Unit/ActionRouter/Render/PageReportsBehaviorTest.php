<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReports;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	InstantAlerts,
	Reporting
};

class PageReportsBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	private object $zoneCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_list_subnav_renders_reports_view_and_create_action() :void {
		$page = new PageReports( [
			'nav_sub' => PluginNavs::SUBNAV_REPORTS_LIST,
		] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextualHrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertSame(
			[
				[
					'action'     => Reports\PageReportsView::class,
					'action_data' => [],
				],
			],
			$this->renderCapture->calls
		);
		$this->assertSame( 'rendered-1', $renderData[ 'content' ][ 'create_report' ] ?? '' );
		$this->assertSame( 'View & Create', $renderData[ 'strings' ][ 'inner_page_title' ] ?? '' );
		$this->assertSame( 'View and create new security reports.', $renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? '' );

		$this->assertCount( 1, $contextualHrefs );
		$this->assertSame( 'Create Custom Report', $contextualHrefs[ 0 ][ 'title' ] ?? '' );
		$this->assertSame( [ 'offcanvas_report_create_form' ], $contextualHrefs[ 0 ][ 'classes' ] ?? [] );
	}

	public function test_charts_subnav_renders_summary_charts_without_create_action() :void {
		$page = new PageReports( [
			'nav_sub' => PluginNavs::SUBNAV_REPORTS_CHARTS,
		] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextualHrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertSame(
			[
				[
					'action'     => Reports\ChartsSummary::class,
					'action_data' => [],
				],
			],
			$this->renderCapture->calls
		);
		$this->assertSame( 'rendered-1', $renderData[ 'content' ][ 'summary_charts' ] ?? '' );
		$this->assertSame( 'Charts & Trends', $renderData[ 'strings' ][ 'inner_page_title' ] ?? '' );
		$this->assertSame( 'Review recent security trend metrics.', $renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? '' );
		$this->assertSame( [], $contextualHrefs );
	}

	public function test_settings_subnav_renders_alert_settings_without_create_action() :void {
		$page = new PageReports( [
			'nav_sub' => PluginNavs::SUBNAV_REPORTS_SETTINGS,
		] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextualHrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertSame(
			PluginNavs::reportsSettingsZoneComponentSlugs(),
			$this->zoneCapture->requested
		);
		$this->assertSame(
			[
				[
					'action'     => OptionsFormFor::class,
					'action_data' => [
						'options' => [
							InstantAlerts::Slug().'-opt-a',
							InstantAlerts::Slug().'-opt-b',
							Reporting::Slug().'-opt-a',
							Reporting::Slug().'-opt-b',
						],
					],
				],
			],
			$this->renderCapture->calls
		);
		$this->assertSame( 'rendered-1', $renderData[ 'content' ][ 'alerts_settings' ] ?? '' );
		$this->assertSame( 'Alert Settings', $renderData[ 'strings' ][ 'inner_page_title' ] ?? '' );
		$this->assertSame( 'Manage instant alerts and report delivery settings.', $renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? '' );
		$this->assertSame( [], $contextualHrefs );
	}

	public function test_unknown_subnav_preserves_fallback_title_subtitle_and_empty_content() :void {
		$page = new PageReports( [
			'nav_sub' => 'unknown-subnav',
		] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextualHrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertSame( [], $this->renderCapture->calls );
		$this->assertSame( [], $this->zoneCapture->requested );
		$this->assertSame( [], $renderData[ 'content' ] ?? [] );
		$this->assertSame(
			CommonDisplayStrings::get( 'security_reports_label' ),
			$renderData[ 'strings' ][ 'inner_page_title' ] ?? ''
		);
		$this->assertSame( 'Summary Security Reports.', $renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? '' );
		$this->assertSame( [], $contextualHrefs );
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];
		$this->zoneCapture = (object)[
			'requested' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->caps = new class {
			public function canReportsLocal() :bool {
				return true;
			}
		};
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
				$this->capture->calls[] = [
					'action'     => $action,
					'action_data' => $actionData,
				];
				return 'rendered-'.\count( $this->capture->calls );
			}
		};
		$controller->comps = (object)[
			'zones' => new class( $this->zoneCapture ) {
				private object $capture;

				public function __construct( object $capture ) {
					$this->capture = $capture;
				}

				public function getZoneComponent( string $slug ) :object {
					$this->capture->requested[] = $slug;
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

		PluginControllerInstaller::install( $controller );
	}
}
