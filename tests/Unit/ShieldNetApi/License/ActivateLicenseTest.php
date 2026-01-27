<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\License;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\License\ActivateLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for ActivateLicense ShieldNet API client
 */
class ActivateLicenseTest extends BaseUnitTest {

	public function testApiActionIsCorrect() :void {
		$this->assertEquals( 'licenses/activate', ActivateLicense::API_ACTION );
	}

	public function testExtendsBaseShieldNetApiV2() :void {
		$api = new ActivateLicense();
		$this->assertInstanceOf( BaseShieldNetApiV2::class, $api );
	}

	public function testActivateMethodExists() :void {
		$api = new ActivateLicense();
		$this->assertTrue( \method_exists( $api, 'activate' ) );
	}
}
