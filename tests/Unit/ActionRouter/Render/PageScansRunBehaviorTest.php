<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageScansRun;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageScansRunBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_contextual_href_points_to_actions_queue_scans() :void {
		$page = new PageScansRun();
		$hrefs = $this->invokeNonPublicMethod( $page, 'getPageContextualHrefs' );

		$this->assertCount( 1, $hrefs );
		$this->assertSame( 'Scan Results', (string)( $hrefs[ 0 ][ 'title' ] ?? '' ) );
		$this->assertSame( '/admin/scans/overview?zone=scans', (string)( $hrefs[ 0 ][ 'href' ] ?? '' ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function actionsQueueScans() :string {
				return '/admin/scans/overview?zone=scans';
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
