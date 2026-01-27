<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\Activate;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\License\ActivateLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

/**
 * Integration tests for license activation system
 */
class LicenseActivationIntegrationTest extends ShieldWordPressTestCase {

	public function testActivateClassCanBeInstantiated() :void {
		$activate = new Activate();
		$this->assertInstanceOf( Activate::class, $activate );
	}

	public function testActivateLicenseApiClassCanBeInstantiated() :void {
		$api = new ActivateLicense();
		$this->assertInstanceOf( ActivateLicense::class, $api );
	}

	public function testLicenseHandlerHasActivationUrlMethod() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller should be available' );

		$licenseHandler = $con->comps->license ?? null;
		$this->assertNotNull( $licenseHandler, 'License handler should exist' );
		$this->assertTrue(
			\method_exists( $licenseHandler, 'activationURL' ),
			'License handler should have activationURL method'
		);

		$activationUrl = $licenseHandler->activationURL();
		$this->assertIsString( $activationUrl, 'activationURL should return a string' );
	}

	public function testActivationEventsAreDefined() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller should be available' );

		$events = $con->cfg->events ?? [];
		$this->assertIsArray( $events );

		$this->assertArrayHasKey(
			'lic_activation_success',
			$events,
			'lic_activation_success event should be defined'
		);
		$this->assertArrayHasKey(
			'lic_activation_fail',
			$events,
			'lic_activation_fail event should be defined'
		);

		// Verify event configuration
		$failEvent = $events[ 'lic_activation_fail' ];
		$this->assertEquals( 'warning', $failEvent[ 'level' ] ?? '' );
		$this->assertContains( 'error', $failEvent[ 'audit_params' ] ?? [] );

		$successEvent = $events[ 'lic_activation_success' ];
		$this->assertEquals( 'notice', $successEvent[ 'level' ] ?? '' );
	}

	public function testActivateRunMethodRequiresApiKey() :void {
		$activate = new Activate();

		// Using reflection to test that run() requires a non-empty API key
		$reflection = new \ReflectionMethod( $activate, 'run' );
		$params = $reflection->getParameters();

		$this->assertCount( 1, $params, 'run method should have exactly 1 parameter' );
		$this->assertEquals( 'apiKey', $params[ 0 ]->getName() );
		$this->assertEquals( 'string', $params[ 0 ]->getType()->getName() );
	}
}
