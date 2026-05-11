<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\IPs\Lib\Bots;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalNames;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class BotSignalNamesTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text === '%1$s v%2$s Registration' ? '[version=%2$s][brand=%1$s]' : $text );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = new class {
			public function getBrandName( string $brand ) :string {
				return $brand === 'silentcaptcha' ? 'silentCAPTCHA' : $brand;
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();

		parent::tearDown();
	}

	public function test_silentcaptcha_signal_names_use_public_versions() :void {
		$names = ( new BotSignalNames() )->getBotSignalNames();

		$this->assertSame( '[version=1][brand=silentCAPTCHA]', $names[ 'notbot' ] ?? null );
		$this->assertSame( '[version=3][brand=silentCAPTCHA]', $names[ 'altcha' ] ?? null );
	}
}
