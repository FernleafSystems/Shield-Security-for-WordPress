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
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ExternalLinks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SelectSearchData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class SelectSearchDataExternalLinksTest extends BaseUnitTest {

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

	public function testExternalSearchUsesSharedExternalLinksCatalog() :void {
		$this->installControllerStubs( 'https://help.example.com' );

		$searchData = new SelectSearchData();
		$method = new \ReflectionMethod( $searchData, 'getExternalSearch' );
		$method->setAccessible( true );
		$groups = $method->invoke( $searchData );

		$this->assertCount( 1, $groups );

		$childrenById = [];
		foreach ( $groups[ 0 ][ 'children' ] as $child ) {
			$childrenById[ $child[ 'id' ] ] = $child;
		}

		$links = new ExternalLinks();
		$this->assertArrayHasKey( 'external_helpdesk', $childrenById );
		$this->assertSame( $links->url( ExternalLinks::HELPDESK ), $childrenById[ 'external_helpdesk' ][ 'link' ][ 'href' ] );
	}

	private function installControllerStubs( string $helpdeskUrl ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = (object)[
			'url_helpdesk' => $helpdeskUrl,
			'Name'         => 'Shield Security',
		];
		$controller->svgs = new class {
			public function iconClass( string $slug ) :string {
				return 'icon-'.$slug;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
