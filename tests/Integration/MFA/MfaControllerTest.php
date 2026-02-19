<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\MfaController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests MfaController: provider enumeration, per-user provider checks,
 * and login intent management.
 */
class MfaControllerTest extends ShieldIntegrationTestCase {

	private function mfa() :MfaController {
		return $this->requireController()->comps->mfa;
	}

	public function test_provider_enumeration_returns_expected_set() {
		$classes = $this->mfa()->collateMfaProviderClasses();

		$this->assertIsArray( $classes );
		$this->assertNotEmpty( $classes, 'MFA should have at least one provider' );

		// All entries should be class strings implementing Provider2faInterface
		foreach ( $classes as $class ) {
			$this->assertTrue( \class_exists( $class ),
				"Provider class {$class} should exist" );
		}
	}

	public function test_providers_for_new_user_without_mfa() {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		// A new user without MFA configured should have no active providers
		$active = $this->mfa()->getProvidersActiveForUser( $user );
		$this->assertEmpty( $active, 'New user should have no active MFA providers' );
	}

	public function test_is_not_subject_to_login_intent_without_providers() {
		$userId = self::factory()->user->create( [ 'role' => 'editor' ] );
		$user = \get_user_by( 'id', $userId );

		$this->assertFalse( $this->mfa()->isSubjectToLoginIntent( $user ),
			'User without active MFA should not be subject to login intent' );
	}

	public function test_remove_all_factors_for_nonexistent_user() {
		$result = $this->mfa()->removeAllFactorsForUser( 999999 );
		$this->assertFalse( $result->success );
		$this->assertNotEmpty( $result->error_text );
	}

	public function test_remove_all_factors_for_real_user() {
		$userId = $this->createAdministratorUser();
		$result = $this->mfa()->removeAllFactorsForUser( $userId );

		// Even with no MFA configured, this should succeed (no-op)
		$this->assertTrue( $result->success );
	}

	public function test_active_login_intents_empty_for_fresh_user() {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$intents = $this->mfa()->getActiveLoginIntents( $user );
		$this->assertIsArray( $intents );
		$this->assertEmpty( $intents, 'Fresh user should have no active login intents' );
	}

	public function test_verify_login_nonce_fails_for_invalid() {
		$this->captureShieldEvents();

		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );

		$result = $this->mfa()->verifyLoginNonce( $user, 'completely-invalid-nonce' );
		$this->assertFalse( $result );

		$events = $this->getCapturedEventsByKey( '2fa_nonce_verify_fail' );
		$this->assertNotEmpty( $events, '2fa_nonce_verify_fail event should fire for invalid nonce' );
	}
}
