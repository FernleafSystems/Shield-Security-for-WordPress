<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageIpRulesTable;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageIpRulesTableBehaviorTest extends BaseUnitTest {

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

	public function test_contextual_create_rule_action_uses_action_control_contract() :void {
		$contextualHrefs = $this->invokeNonPublicMethod( new PageIpRulesTable(), 'getPageContextualHrefs' );

		$this->assertSame( '', $contextualHrefs[ 0 ][ 'href' ] ?? 'unexpected' );
		$this->assertTrue( $contextualHrefs[ 0 ][ 'is_action' ] ?? false );
		$this->assertSame( [ 'offcanvas_form_create_ip_rule' ], $contextualHrefs[ 0 ][ 'classes' ] ?? [] );
		$this->assertNotSame( '', $contextualHrefs[ 1 ][ 'href' ] ?? '' );
		$this->assertFalse( $contextualHrefs[ 1 ][ 'is_action' ] ?? false );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function fileDownloadAsStream( string $stream ) :string {
				return '/download/'.$stream.'.csv';
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class {
			public function render( string $action, array $actionData = [] ) :string {
				return '';
			}
		};
		$controller->comps = (object)[
			'license' => new class {
				public function hasValidWorkingLicense() :bool {
					return true;
				}
			},
		];

		PluginControllerInstaller::install( $controller );
	}
}
