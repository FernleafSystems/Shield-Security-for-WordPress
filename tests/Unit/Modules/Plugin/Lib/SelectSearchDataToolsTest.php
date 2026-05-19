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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\StaticToolDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class SelectSearchDataToolsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias(
			fn( string $text ) :string => $text
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_tools_search_uses_canonical_tool_routes() :void {
		$pluginUrls = $this->installControllerStubs();

		$searchData = new SelectSearchData();
		$method = new \ReflectionMethod( $searchData, 'getToolsSearch' );
		$method->setAccessible( true );
		$groups = $method->invoke( $searchData );

		$this->assertCount( 1, $groups );

		$childrenById = [];
		foreach ( $groups[ 0 ][ 'children' ] as $child ) {
			$childrenById[ $child[ 'id' ] ] = $child;
		}

		$definitionsById = [];
		foreach ( StaticToolDefinitions::forSearch() as $definition ) {
			$definitionsById[ $definition[ 'id' ] ] = $definition;
		}

		$this->assertSame( \array_keys( $definitionsById ), \array_keys( $childrenById ) );

		foreach ( $definitionsById as $toolId => $definition ) {
			$this->assertArrayHasKey( $toolId, $definitionsById );
			$this->assertArrayHasKey( $toolId, $childrenById );
			$this->assertSame(
				$pluginUrls->adminTopNav( $definition[ 'nav' ], $definition[ 'subnav' ] ),
				$childrenById[ $toolId ][ 'link' ][ 'href' ] ?? ''
			);
			$this->assertSame( (string)$definition[ 'search_tokens' ], $childrenById[ $toolId ][ 'tokens' ] ?? '' );
			$this->assertSame( 'icon-'.$definition[ 'icon' ].'.svg', $childrenById[ $toolId ][ 'icon' ] ?? '' );
			$this->assertNotSame( '', \trim( (string)( $childrenById[ $toolId ][ 'text' ] ?? '' ) ) );
		}
	}

	private function installControllerStubs() :object {
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
		$pluginUrls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->plugin_urls = $pluginUrls;

		PluginControllerInstaller::install( $controller );

		return $pluginUrls;
	}
}
