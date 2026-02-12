<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Activate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Exceptions\FailedLicenseRequestHttpException;
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

	public function testActivateThrowsWhenRequestFails() :void {
		$api = new class extends ActivateLicense {
			protected function sendReq() :?array {
				return null;
			}
		};

		$this->expectException( FailedLicenseRequestHttpException::class );
		$api->activate( 'any-key', 'https://example.com' );
	}

	public function testActivateReturnsApiResponseArray() :void {
		$expected = [
			'error_code' => Activate::ERR_ALREADY_ACTIVATED,
			'message'    => 'Already activated',
		];

		$api = new class( $expected ) extends ActivateLicense {

			private array $response;

			public function __construct( array $response ) {
				$this->response = $response;
			}

			protected function sendReq() :?array {
				return $this->response;
			}
		};

		$this->assertSame( $expected, $api->activate( 'any-key', 'https://example.com' ) );
	}
}
