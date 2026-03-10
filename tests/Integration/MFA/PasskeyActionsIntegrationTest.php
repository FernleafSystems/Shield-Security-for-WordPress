<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaPasskeyAuthenticationStart,
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

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertNotEmpty( $payload[ 'challenge' ][ 'challenge' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::requestHost(), $payload[ 'challenge' ][ 'rp' ][ 'id' ] ?? '' );
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

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );

		$records = $this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult();
		$this->assertCount( 1, $records );
		$this->assertSame( Passkey::ProviderSlug(), $records[ 0 ]->slug );
		$this->assertSame( 'Desk Key', $records[ 0 ]->label );
		$this->assertSame( PasskeyFixtureLoader::registrationCredentialId(), $records[ 0 ]->data[ 'publicKeyCredentialId' ] ?? '' );
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

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertNotEmpty( $payload[ 'challenge' ][ 'challenge' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::credentialId(), $payload[ 'challenge' ][ 'allowCredentials' ][ 0 ][ 'id' ] ?? '' );
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

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult()
		);
	}
}
