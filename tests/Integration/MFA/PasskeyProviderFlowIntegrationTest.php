<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\OtpVerificationFailedException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginIntentRequestValidate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\{
	PasskeyFixtureLoader,
	PasskeyTestEnvironmentTrait
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PasskeyProviderFlowIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_registration_verification_persists_passkey_record() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedPasskeyRegistrationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyRegistrationResponse( PasskeyFixtureLoader::registrationResponse(), 'Desk Key' );

		$this->assertTrue( $result->success );

		$records = $this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $user->ID )->queryWithResult();
		$this->assertCount( 1, $records );
		$this->assertSame( Passkey::ProviderSlug(), $records[ 0 ]->slug );
		$this->assertSame( 'Desk Key', $records[ 0 ]->label );
		$this->assertTrue( (bool)$records[ 0 ]->passwordless );
		$this->assertSame( PasskeyFixtureLoader::registrationCredentialUniqueId(), $records[ 0 ]->unique_id );
		$this->assertSame( PasskeyFixtureLoader::registrationCredentialId(), $records[ 0 ]->data[ 'publicKeyCredentialId' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::registrationExpectedCounter(), (int)( $records[ 0 ]->data[ 'counter' ] ?? 0 ) );
	}

	public function test_registration_verification_rejects_wrong_challenge() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$options = PasskeyFixtureLoader::registrationOptions();
		$options[ 'challenge' ] = $this->randomBase64Url();
		$this->seedPasskeyRegistrationOptions( $user, $options );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyRegistrationResponse( PasskeyFixtureLoader::registrationResponse(), 'Desk Key' );

		$this->assertFalse( $result->success );
		$this->assertEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $user->ID )->queryWithResult()
		);
	}

	public function test_registration_verification_rejects_wrong_origin() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedPasskeyRegistrationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyRegistrationResponse(
			PasskeyFixtureLoader::registrationResponse( [], [
				'origin' => 'https://evil.example',
			] ),
			'Desk Key'
		);

		$this->assertFalse( $result->success );
		$this->assertEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $user->ID )->queryWithResult()
		);
	}

	public function test_registration_verification_rejects_malformed_payload() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedPasskeyRegistrationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyRegistrationResponse( '{"id":"broken"}', 'Desk Key' );

		$this->assertFalse( $result->success );
		$this->assertEmpty(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $user->ID )->queryWithResult()
		);
	}

	public function test_authentication_verification_updates_used_at_and_counter() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recordId = $this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyAuthResponse( PasskeyFixtureLoader::authenticationResponse() );

		$this->assertTrue( $result->success );

		$record = $this->requireController()->db_con->mfa->getQuerySelector()->byId( $recordId );
		$this->assertGreaterThan( 0, (int)$record->used_at );
		$this->assertSame( PasskeyFixtureLoader::authenticationExpectedCounter(), (int)( $record->data[ 'counter' ] ?? 0 ) );
	}

	public function test_authentication_verification_rejects_wrong_origin() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recordId = $this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyAuthResponse(
			PasskeyFixtureLoader::authenticationResponse( [], [
				'origin' => 'https://evil.example',
			] )
		);

		$this->assertFalse( $result->success );

		$record = $this->requireController()->db_con->mfa->getQuerySelector()->byId( $recordId );
		$this->assertSame( 0, (int)$record->used_at );
		$this->assertSame( PasskeyFixtureLoader::legacyRecordCounter(), (int)( $record->data[ 'counter' ] ?? 0 ) );
	}

	public function test_authentication_verification_rejects_wrong_challenge() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recordId = $this->seedLegacyPasskey( $user );
		$options = PasskeyFixtureLoader::authenticationOptions();
		$options[ 'challenge' ] = $this->randomBase64Url();
		$this->seedPasskeyAuthenticationOptions( $user, $options );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyAuthResponse( PasskeyFixtureLoader::authenticationResponse() );

		$this->assertFalse( $result->success );

		$record = $this->requireController()->db_con->mfa->getQuerySelector()->byId( $recordId );
		$this->assertSame( 0, (int)$record->used_at );
		$this->assertSame( PasskeyFixtureLoader::legacyRecordCounter(), (int)( $record->data[ 'counter' ] ?? 0 ) );
	}

	public function test_authentication_verification_rejects_unknown_credential() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recordId = $this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyAuthResponse( PasskeyFixtureLoader::authenticationResponse( [
			'id'    => $this->randomBase64Url(),
			'rawId' => $this->randomBase64Url(),
		] ) );

		$this->assertFalse( $result->success );

		$record = $this->requireController()->db_con->mfa->getQuerySelector()->byId( $recordId );
		$this->assertSame( 0, (int)$record->used_at );
		$this->assertSame( PasskeyFixtureLoader::legacyRecordCounter(), (int)( $record->data[ 'counter' ] ?? 0 ) );
	}

	public function test_authentication_verification_rejects_malformed_payload() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$recordId = $this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );

		$provider = $this->createPasskeyProvider( $user );
		$result = $provider->verifyAuthResponse( '{"id":"broken"}' );

		$this->assertFalse( $result->success );

		$record = $this->requireController()->db_con->mfa->getQuerySelector()->byId( $recordId );
		$this->assertSame( 0, (int)$record->used_at );
		$this->assertSame( PasskeyFixtureLoader::legacyRecordCounter(), (int)( $record->data[ 'counter' ] ?? 0 ) );
	}

	public function test_login_intent_validation_succeeds_for_existing_passkey_record() :void {
		$this->captureShieldEvents();

		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );
		$this->seedLoginIntent( $user, 'fixture-passkey-login' );

		$provider = $this->createPasskeyProvider( $user );
		$this->setPasskeyLoginOtpRequest( $provider, PasskeyFixtureLoader::authenticationResponse() );

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'fixture-passkey-login' );

		$this->assertSame( Passkey::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_2fa_verified_at );
		$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_success' ) );
	}

	public function test_login_intent_validation_fails_for_invalid_passkey_payload() :void {
		$this->captureShieldEvents();

		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );
		$this->seedPasskeyAuthenticationOptions( $user );
		$this->seedLoginIntent( $user, 'fixture-passkey-login' );

		$provider = $this->createPasskeyProvider( $user );
		$this->setPasskeyLoginOtpRequest(
			$provider,
			PasskeyFixtureLoader::authenticationResponse( [], [
				'origin' => 'https://evil.example',
			] )
		);

		$this->expectException( OtpVerificationFailedException::class );

		try {
			( new LoginIntentRequestValidate() )
				->setWpUser( $user )
				->run( 'fixture-passkey-login' );
		}
		finally {
			$this->assertNotEmpty( $this->getCapturedEventsByKey( '2fa_verify_fail' ) );
			$this->assertNotEmpty( $this->requireController()->user_metas->for( $user )->login_intents );
		}
	}

	private function randomBase64Url( int $length = 32 ) :string {
		return \rtrim( \strtr( \base64_encode( \random_bytes( $length ) ), '+/', '-_' ), '=' );
	}
}
