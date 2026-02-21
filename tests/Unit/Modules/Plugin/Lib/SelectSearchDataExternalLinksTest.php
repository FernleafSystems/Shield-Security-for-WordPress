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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;

class SelectSearchDataExternalLinksTest extends BaseUnitTest {

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

	public function testExternalSearchUsesSharedExternalLinksCatalog() :void {
		$this->installControllerStubs( 'https://help.example.com' );

		$searchData = new SelectSearchData();
		$method = new \ReflectionMethod( $searchData, 'getExternalSearch' );
		$method->setAccessible( true );
		$groups = $method->invoke( $searchData );

		$this->assertCount( 1, $groups );
		$this->assertSame( 'External Links', $groups[ 0 ][ 'text' ] );

		$childrenById = [];
		foreach ( $groups[ 0 ][ 'children' ] as $child ) {
			$childrenById[ $child[ 'id' ] ] = $child;
		}
		$this->assertCount( 7, $childrenById );

		$links = new ExternalLinks();
		$this->assertSame( $links->url( ExternalLinks::HELPDESK ), $childrenById[ 'external_helpdesk' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::HOME ), $childrenById[ 'external_getshieldhome' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::GOPRO ), $childrenById[ 'external_gopro' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::FREE_TRIAL ), $childrenById[ 'external_trial' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::REVIEW ), $childrenById[ 'external_review' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::TESTIMONIALS ), $childrenById[ 'external_testimonials' ][ 'link' ][ 'href' ] );
		$this->assertSame( $links->url( ExternalLinks::CROWDSEC ), $childrenById[ 'external_crowdsec' ][ 'link' ][ 'href' ] );
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

