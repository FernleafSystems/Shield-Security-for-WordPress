<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\AdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AdminNoticeTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( AdminNotice::class ) );
	}

	/**
	 * @dataProvider providerWpNoticeClassMappings
	 */
	public function testWpNoticeClassMappings( string $type, string $expectedClass ) :void {
		$component = new AdminNotice();
		$reflection = new \ReflectionClass( $component );
		$method = $reflection->getMethod( 'getWpNoticeClass' );
		$method->setAccessible( true );

		$this->assertSame( $expectedClass, $method->invoke( $component, $type ) );
	}

	public static function providerWpNoticeClassMappings() :array {
		return [
			'error maps to notice-error' => [ 'error', 'notice-error' ],
			'warning maps to notice-warning' => [ 'warning', 'notice-warning' ],
			'updated maps to notice-success' => [ 'updated', 'notice-success' ],
			'success maps to notice-success' => [ 'success', 'notice-success' ],
			'promo maps to notice-info' => [ 'promo', 'notice-info' ],
			'unknown maps to notice-info' => [ 'foo', 'notice-info' ],
		];
	}
}
