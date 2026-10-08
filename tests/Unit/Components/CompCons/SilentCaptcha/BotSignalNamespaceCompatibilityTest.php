<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\SilentCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class BotSignalNamespaceCompatibilityTest extends BaseUnitTest {

	#[\PHPUnit\Framework\Attributes\DataProvider( 'signalClasses' )]
	public function test_legacy_namespace_resolves_to_the_same_component( string $name ) :void {
		$legacy = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Lib\\Bots\\'.$name;
		$canonical = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Components\\CompCons\\SilentCaptcha\\Signals\\'.$name;
		$this->assertTrue( \class_exists( $legacy ) );
		$this->assertSame( $canonical, \get_class( new $legacy() ) );
		$this->assertInstanceOf( $legacy, new $canonical() );
	}

	public static function signalClasses() :array {
		return [
			[ 'BotSignalsController' ],
			[ 'BotSignalsRecord' ],
			[ 'BotEventListener' ],
			[ 'BotSignalNames' ],
		];
	}
}
