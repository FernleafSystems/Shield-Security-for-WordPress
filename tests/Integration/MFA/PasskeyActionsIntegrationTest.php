<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaPasskeyAuthenticationStart,
	MfaPasskeyAuthenticationVerify,
	MfaPasskeyRegistrationStart,
	MfaPasskeyRegistrationVerify,
	MfaPasskeyRemoveSource
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\{
	PasskeyFixtureLoader,
	PasskeyTestEnvironmentTrait
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PasskeyActionsIntegrationTest extends ShieldIntegrationTestCase {

	use PasskeyTestEnvironmentTrait;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->setUpPasskeyEnvironment();
	}

	public function tear_down() {
		$this->tearDownPasskeyEnvironment();
		parent::tear_down();
	}

	public function test_registration_start_action_returns_challenge_payload() :void {
		$this->loginAsAdministrator();

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyRegistrationStart::class,
			[],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$challenge = $this->assertChallengePayload( $payload );
		$this->assertArrayHasKey( 'rp', $challenge );
		$this->assertIsArray( $challenge[ 'rp' ] );
		$this->assertSame( PasskeyFixtureLoader::requestHost(), $challenge[ 'rp' ][ 'id' ] );
		$this->assertArrayHasKey( 'passkey_label', $payload );
	}

	public function test_registration_verify_action_persists_passkey_record() :void {
		$userId = $this->loginAsAdministrator();
		$user = \get_user_by( 'id', $userId );
		$this->seedPasskeyRegistrationOptions( $user );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyRegistrationVerify::class,
			[
				'reg'   => PasskeyFixtureLoader::registrationResponse(),
				'label' => 'Desk Key',
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertSuccessfulPayload( $payload );

		$records = $this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult();
		$this->assertCount( 1, $records );
		$this->assertSame( Passkey::ProviderSlug(), $records[ 0 ]->slug );
		$this->assertSame( 'Desk Key', $records[ 0 ]->label );
		$this->assertArrayHasKey( 'publicKeyCredentialId', $records[ 0 ]->data );
		$this->assertSame( PasskeyFixtureLoader::registrationCredentialId(), $records[ 0 ]->data[ 'publicKeyCredentialId' ] );
	}

	public function test_authentication_start_action_returns_challenge_for_valid_login_intent() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );
		$this->seedLoginIntent( $user, 'fixture-login-nonce' );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyAuthenticationStart::class,
			[
				'login_wp_user' => $userId,
				'login_nonce'   => 'fixture-login-nonce',
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$challenge = $this->assertChallengePayload( $payload );
		$this->assertArrayHasKey( 'allowCredentials', $challenge );
		$this->assertIsArray( $challenge[ 'allowCredentials' ] );
		$this->assertArrayHasKey( 0, $challenge[ 'allowCredentials' ] );
		$this->assertSame( PasskeyFixtureLoader::credentialId(), $challenge[ 'allowCredentials' ][ 0 ][ 'id' ] );
	}

	public function test_login_field_render_does_not_create_authentication_challenge() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$field = $this->createPasskeyProvider( $user )->getFormField();
		$passkeys = $this->requireController()->user_metas->for( $user )->passkeys;

		$this->assertArrayHasKey( 'datas', $field );
		$this->assertSame( [], $field[ 'datas' ] );
		$this->assertIsArray( $passkeys );
		$this->assertArrayNotHasKey( 'auth_challenge', $passkeys );
	}

	public function test_authentication_verify_action_accepts_legacy_ajax_payload_for_valid_login_intent() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );
		$this->seedLoginIntent( $user, 'fixture-login-nonce' );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyAuthenticationVerify::class,
			[
				'login_wp_user' => $userId,
				'login_nonce'   => 'fixture-login-nonce',
				'auth'          => PasskeyFixtureLoader::authenticationResponse(),
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertSuccessfulPayload( $payload );
	}

	public function test_authentication_verify_action_rejects_empty_legacy_ajax_payload() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );
		$this->seedLoginIntent( $user, 'fixture-login-nonce' );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyAuthenticationVerify::class,
			[
				'login_wp_user' => $userId,
				'login_nonce'   => 'fixture-login-nonce',
				'auth'          => '',
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertFailedPayload( $payload );
	}

	public function test_remove_source_action_deletes_existing_passkey_record() :void {
		$userId = $this->loginAsAdministrator();
		$this->insertLegacyPasskeyActionRecord( $userId );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyRemoveSource::class,
			[
				'wan_source_id' => PasskeyFixtureLoader::credentialUniqueId(),
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertSuccessfulPayload( $payload );
		$this->assertEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult()
		);
	}

	public function test_remove_source_action_rejects_unknown_passkey_id() :void {
		$userId = $this->loginAsAdministrator();
		$this->insertLegacyPasskeyActionRecord( $userId );

		$routed = $this->requireController()->action_router->action(
			MfaPasskeyRemoveSource::class,
			[
				'wan_source_id' => 'unknown-passkey-id',
			],
			ActionRoutingController::ACTION_SHIELD
		);
		$payload = $routed->payload();

		$this->assertFailedPayload( $payload );
		$this->assertNotEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult()
		);
	}

	private function assertSuccessfulPayload( array $payload ) :void {
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( $payload[ 'success' ] );
	}

	private function assertFailedPayload( array $payload ) :void {
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
	}

	private function assertChallengePayload( array $payload ) :array {
		$this->assertSuccessfulPayload( $payload );
		$this->assertArrayHasKey( 'challenge', $payload );
		$this->assertIsArray( $payload[ 'challenge' ] );
		$this->assertArrayHasKey( 'challenge', $payload[ 'challenge' ] );
		$this->assertNotSame( '', $payload[ 'challenge' ][ 'challenge' ] );
		return $payload[ 'challenge' ];
	}
}
