<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;

class SelectSearchDataToolsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
	}

	protected function tearDown() :void {
		PluginStore::$plugin = null;
		parent::tearDown();
	}

	public function test_tools_search_uses_canonical_tool_routes() :void {
		$this->installControllerStubs();

		$searchData = new SelectSearchData();
		$method = new \ReflectionMethod( $searchData, 'getToolsSearch' );
		$method->setAccessible( true );
		$groups = $method->invoke( $searchData );

		$this->assertCount( 1, $groups );

		$childrenById = [];
		foreach ( $groups[ 0 ][ 'children' ] as $child ) {
			$childrenById[ $child[ 'id' ] ] = $child;
		}

		$this->assertSame( '/admin/merlin/welcome', $childrenById[ 'tool_guidedsetup' ][ 'link' ][ 'href' ] ?? '' );
		$this->assertSame( '/admin/tools/debug', $childrenById[ 'tool_debug' ][ 'link' ][ 'href' ] ?? '' );
		$this->assertSame( '/admin/reports/overview', $childrenById[ 'tool_reports' ][ 'link' ][ 'href' ] ?? '' );
		$this->assertSame( '/admin/activity/sessions', $childrenById[ 'tool_sessions' ][ 'link' ][ 'href' ] ?? '' );
	}

	private function installControllerStubs() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = (object)[
			'Name' => 'Shield Security',
		];
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};
		$controller->plugin_urls = new class {
			public function adminHome() :string {
				return '/admin/home';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function adminIpRules() :string {
				return '/admin/ips/rules';
			}

			public function investigateUserSessions() :string {
				return '/admin/activity/sessions';
			}

			public function wizard( string $wizardKey ) :string {
				return '/admin/wizard/'.$wizardKey;
			}
		};

		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};
	}
}
