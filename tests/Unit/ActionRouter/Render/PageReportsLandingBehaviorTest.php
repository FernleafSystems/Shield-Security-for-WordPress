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
	ChartsSummary,
	PageReportsView,
	ReportsTable
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReportsLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageReportsLandingBehaviorTest extends BaseUnitTest {

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

	public function test_landing_content_renders_charts_summary_and_reports_table() :void {
		$page = new PageReportsLanding();
		$content = $this->invokeProtectedMethod( $page, 'getLandingContent' );

		$this->assertSame(
			[
				[
					'action'     => ChartsSummary::class,
					'action_data' => [],
				],
				[
					'action'     => ReportsTable::class,
					'action_data' => [
						'reports_limit' => 5,
					],
				],
			],
			$this->renderCapture->calls
		);
		$this->assertSame( 'rendered-1', $content[ 'summary_charts' ] ?? '' );
		$this->assertSame( 'rendered-2', $content[ 'recent_reports' ] ?? '' );
		$this->assertNotContains( PageReportsView::class, \array_column( $this->renderCapture->calls, 'action' ) );
	}

	public function test_landing_hrefs_include_list_charts_and_settings() :void {
		$page = new PageReportsLanding();
		$hrefs = $this->invokeProtectedMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'reports_list'     => '/admin/reports/list',
				'reports_charts'   => '/admin/reports/charts',
				'reports_settings' => '/admin/reports/settings',
			],
			$hrefs
		);
	}

	public function test_landing_strings_include_list_charts_and_settings_ctas() :void {
		$page = new PageReportsLanding();
		$strings = $this->invokeProtectedMethod( $page, 'getLandingStrings' );

		$this->assertSame( 'Open Reports List', $strings[ 'cta_reports_list' ] ?? '' );
		$this->assertSame( 'Open Charts & Trends', $strings[ 'cta_reports_charts' ] ?? '' );
		$this->assertSame( 'Open Alert Settings', $strings[ 'cta_reports_settings' ] ?? '' );
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
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	private function invokeProtectedMethod( object $subject, string $methodName ) {
		$method = new \ReflectionMethod( $subject, $methodName );
		$method->setAccessible( true );
		return $method->invoke( $subject );
	}
}
