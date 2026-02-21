<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ExternalLinks;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;

class ExternalLinksTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginStore::$plugin = null;
		parent::tearDown();
	}

	public function testAllProvidesExpectedUrlsIncludingDynamicHelpdesk() :void {
		$this->installControllerStubs( 'https://help.example.com' );

		$links = new ExternalLinks();
		$this->assertSame( 'https://help.example.com', $links->url( ExternalLinks::HELPDESK ) );
		$this->assertSame( 'https://clk.shldscrty.com/shieldsecurityhome', $links->url( ExternalLinks::HOME ) );
		$this->assertSame( 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb', $links->url( ExternalLinks::FACEBOOK_GROUP ) );
		$this->assertSame( 'https://clk.shldscrty.com/emailsubscribe', $links->url( ExternalLinks::NEWSLETTER ) );
		$this->assertSame( 'https://getshieldsecurity.com/pricing/', $links->url( ExternalLinks::GOPRO ) );
		$this->assertSame( 'https://getshieldsecurity.com/free-trial/', $links->url( ExternalLinks::FREE_TRIAL ) );
	}

	public function testUrlReturnsDefaultForUnknownKey() :void {
		$this->installControllerStubs( 'https://help.example.com' );

		$links = new ExternalLinks();
		$this->assertSame( 'fallback', $links->url( 'missing', 'fallback' ) );
	}

	private function installControllerStubs( string $helpdeskUrl ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = (object)[
			'url_helpdesk' => $helpdeskUrl,
		];

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

