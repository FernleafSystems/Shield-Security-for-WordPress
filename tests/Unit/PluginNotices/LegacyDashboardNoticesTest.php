<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\LegacyDashboardNotices;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LegacyDashboardNoticesTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( LegacyDashboardNotices::class ) );
	}

	/**
	 * @dataProvider providerNoticeTypeMappings
	 */
	public function testNormaliseNoticeTypeMappings( string $input, string $expected ) :void {
		$notice = new LegacyDashboardNotices();
		$reflection = new \ReflectionClass( $notice );
		$method = $reflection->getMethod( 'normaliseNoticeType' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( $notice, $input ) );
	}

	public static function providerNoticeTypeMappings() :array {
		return [
			'promo maps to info' => [ 'promo', 'info' ],
			'error maps to danger' => [ 'error', 'danger' ],
			'warning unchanged' => [ 'warning', 'warning' ],
			'info unchanged' => [ 'info', 'info' ],
		];
	}
}
