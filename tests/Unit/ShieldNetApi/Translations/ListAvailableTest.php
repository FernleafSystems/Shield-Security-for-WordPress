<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\Translations;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations\ListAvailable;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for ListAvailable ShieldNet API client
 */
class ListAvailableTest extends BaseUnitTest {

	public function testApiActionIsCorrect() :void {
		$this->assertEquals( 'translations/list', ListAvailable::API_ACTION );
	}

	public function testExtendsBaseShieldNetApiV2() :void {
		$api = new ListAvailable();
		$this->assertInstanceOf( BaseShieldNetApiV2::class, $api );
	}

	public function testRetrieveMethodExists() :void {
		$api = new ListAvailable();
		$this->assertTrue( \method_exists( $api, 'retrieve' ) );
	}
}
