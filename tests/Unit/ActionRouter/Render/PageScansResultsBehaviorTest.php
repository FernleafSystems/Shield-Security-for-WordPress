<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageScansResults;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Utilities\DataManipulation;

class PageScansResultsBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_datamanipulation' => new DataManipulation(),
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_render_data_merges_shared_shell_payload_with_page_strings() :void {
		$page = new PageScansResultsUnitTestDouble( [
			'vars'    => [
				'tabs' => [
					[ 'key' => 'summary' ],
				],
			],
			'content' => [
				'section' => [
					'wordpress' => 'rendered-wordpress',
				],
			],
		] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 1, $page->getBuildCalls() );
		$this->assertSame( 'View Results', (string)( $renderData[ 'strings' ][ 'inner_page_title' ] ?? '' ) );
		$this->assertSame( 'View and manage all scan results.', (string)( $renderData[ 'strings' ][ 'inner_page_subtitle' ] ?? '' ) );
		$this->assertSame( 'bi bi-shield-shaded', (string)( $renderData[ 'imgs' ][ 'inner_page_title_icon' ] ?? '' ) );
		$this->assertSame( [ [ 'key' => 'summary' ] ], $renderData[ 'vars' ][ 'tabs' ] ?? null );
		$this->assertSame( 'rendered-wordpress', (string)( $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? '' ) );
	}

	public function test_contextual_hrefs_keep_results_options_and_run_manual_scan_action() :void {
		$page = new PageScansResultsUnitTestDouble( [] );

		$hrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertCount( 2, $hrefs );
		$this->assertSame( 'Results Display Options', (string)( $hrefs[ 0 ][ 'title' ] ?? '' ) );
		$this->assertSame( 'javascript:{}', (string)( $hrefs[ 0 ][ 'href' ] ?? '' ) );
		$this->assertSame( [ 'offcanvas_form_scans_results_options' ], $hrefs[ 0 ][ 'classes' ] ?? [] );
		$this->assertSame( 'Run Manual Scan', (string)( $hrefs[ 1 ][ 'title' ] ?? '' ) );
		$this->assertSame( '/admin/scans/run', (string)( $hrefs[ 1 ][ 'href' ] ?? '' ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}

class PageScansResultsUnitTestDouble extends PageScansResults {

	private int $buildCalls = 0;
	private array $scansResultsRenderData;

	public function __construct( array $scansResultsRenderData ) {
		$this->scansResultsRenderData = $scansResultsRenderData;
	}

	protected function buildScansResultsRenderData() :array {
		++$this->buildCalls;
		return $this->scansResultsRenderData;
	}

	public function getBuildCalls() :int {
		return $this->buildCalls;
	}
}
