<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Activate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Exceptions\LicenseAlreadyActivatedException;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActivateTest extends BaseUnitTest {

	public function testMapApiErrorCodeToReasonForKnownCodes() :void {
		$this->assertSame( 'invalid_api_key', Activate::mapApiErrorCodeToReason( Activate::ERR_INVALID_API_KEY ) );
		$this->assertSame( 'no_licenses_available', Activate::mapApiErrorCodeToReason( Activate::ERR_NO_LICENSES_AVAILABLE ) );
		$this->assertSame( 'already_activated', Activate::mapApiErrorCodeToReason( Activate::ERR_ALREADY_ACTIVATED ) );
		$this->assertSame( 'activation_denied', Activate::mapApiErrorCodeToReason( Activate::ERR_ACTIVATION_DENIED ) );
	}

	public function testMapApiErrorCodeToReasonUnknownCode() :void {
		$this->assertSame( 'unknown', Activate::mapApiErrorCodeToReason( 999999 ) );
		$this->assertSame( 'unknown', Activate::mapApiErrorCodeToReason( 0 ) );
	}

	public function testIsAlreadyActivatedErrorCode() :void {
		$this->assertTrue( Activate::isAlreadyActivatedErrorCode( Activate::ERR_ALREADY_ACTIVATED ) );
		$this->assertFalse( Activate::isAlreadyActivatedErrorCode( Activate::ERR_INVALID_API_KEY ) );
	}

	public function testPlaceholderErrorCodesAreUnique() :void {
		$codes = [
			Activate::ERR_INVALID_API_KEY,
			Activate::ERR_NO_LICENSES_AVAILABLE,
			Activate::ERR_ALREADY_ACTIVATED,
			Activate::ERR_ACTIVATION_DENIED,
			Activate::ERR_UNKNOWN,
		];
		$this->assertCount( \count( $codes ), \array_unique( $codes ) );
	}

	public function testAlreadyActivatedExceptionClassExists() :void {
		$this->assertTrue( \class_exists( LicenseAlreadyActivatedException::class ) );
	}

	public function testProcessActivationResponseThrowsAlreadyActivatedException() :void {
		$activate = new Activate();
		$reflection = new \ReflectionClass( $activate );
		$method = $reflection->getMethod( 'processActivationResponse' );
		$method->setAccessible( true );

		$this->expectException( LicenseAlreadyActivatedException::class );
		$method->invoke( $activate, [ 'error_code' => Activate::ERR_ALREADY_ACTIVATED ] );
	}
}
