<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use Dolondro\GoogleAuthenticator\GoogleAuthenticator as OtpGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaGoogleAuthToggle;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	LoginIntentRequestValidate
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	ActionRouter\PluginAdminRouteRuntime,
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\{
	ShieldIntegrationTestCase
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class GoogleAuthProviderIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optsSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );

		$this->optsSnapshot = $this->snapshotSelectedOptions( [
			'enable_google_authenticator',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();

		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
		], true );

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
	}

	public function tear_down() :void {
		if ( $this->isControllerConfigReady() ) {
			$this->restoreSelectedOptions( $this->optsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}

		parent::tear_down();
	}

	public function test_reset_secret_generates_32_character_base32_temp_secret() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$provider = new GoogleAuth( $user );

		$this->assertSame( 'ga', GoogleAuth::ProviderSlug() );

		$secret = $provider->resetSecret();

		$this->assertSame( 32, \strlen( $secret ) );
		$this->assertTrue( GoogleAuth::IsValidBase32Secret( $secret ) );
		$this->assertSame( $secret, (string)$this->requireController()->user_metas->for( $user )->ga_temp_secret );
	}

	public function test_enable_google_authenticator_option_gates_provider_availability() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );

		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'N',
		], true );
		RuntimeTestState::resetMfaProviderCache();

		$this->assertFalse( GoogleAuth::ProviderEnabled() );
		$this->assertArrayNotHasKey(
			GoogleAuth::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersAvailableToUser( $user )
		);

		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
		], true );
		RuntimeTestState::resetMfaProviderCache();

		$this->assertTrue( GoogleAuth::ProviderEnabled() );
		$this->assertArrayHasKey(
			GoogleAuth::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersAvailableToUser( $user )
		);
	}

	public function test_activate_ga_persists_new_32_character_secret() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$provider = new GoogleAuth( $user );

		$secret = $provider->resetSecret();
		$result = $provider->activateGA( $this->calculateOtp( $secret ) );

		$this->assertTrue( $result->success, $result->error_text );
		$this->assertCount( 1, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() ) );
		$this->assertSame( $secret, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() )[ 0 ]->unique_id );
		$this->assertEmpty( $this->requireController()->user_metas->for( $user )->ga_temp_secret );
	}

	/**
	 * @dataProvider invalidActivationOtpProvider
	 */
	public function test_invalid_activation_otp_fails_without_creating_record( string $otp ) :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$provider = new GoogleAuth( $user );

		$provider->resetSecret();
		$result = $provider->activateGA( $otp );

		$this->assertFalse( $result->success );
		$this->assertCount( 0, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() ) );
		$this->assertEmpty( $this->requireController()->user_metas->for( $user )->ga_temp_secret );
	}

	public function invalidActivationOtpProvider() :array {
		return [
			'wrong six digits' => [ '000000' ],
			'malformed'        => [ 'not-an-otp' ],
		];
	}

	public function test_profile_toggle_action_activates_and_removes_google_auth_record() :void {
		$userId = $this->loginAsAdministrator();
		$user = \get_user_by( 'id', $userId );
		$provider = new GoogleAuth( $user );
		$secret = $provider->resetSecret();
		$actionData = ActionData::Build( MfaGoogleAuthToggle::class, false );

		$this->assertSame( MfaGoogleAuthToggle::SLUG, (string)( $actionData[ ActionData::FIELD_EXECUTE ] ?? '' ) );

		$activatePayload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaGoogleAuthToggle::SLUG,
			\array_merge( $actionData, [
				'ga_otp' => $this->calculateOtp( $secret ),
			] )
		);

		$this->assertTrue( (bool)( $activatePayload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'message', $activatePayload );
		$this->assertCount( 1, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() ) );

		$removePayload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			MfaGoogleAuthToggle::SLUG,
			$actionData
		);

		$this->assertTrue( (bool)( $removePayload[ 'success' ] ?? false ) );
		$this->assertArrayHasKey( 'message', $removePayload );
		$this->assertCount( 0, $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() ) );
	}

	public function test_new_32_character_secret_validates_through_login_flow() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = $this->activateProviderAndReturnSecret( $user );

		$this->assertLoginValidationForSecret( $user, $secret );
	}

	public function test_activate_ga_makes_provider_active_in_same_request() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = $this->activateProviderAndReturnSecret( $user );

		$this->assertProviderIsActiveForSecret( $user, $secret );
	}

	public function test_existing_16_character_secret_validates_through_login_flow() :void {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		$secret = 'JBSWY3DPEHPK3PXP';

		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => $secret,
			'label'     => 'Existing GA',
		] );

		$this->assertLoginValidationForSecret( $user, $secret );
	}

	public function test_legacy_validated_ga_secret_meta_migrates_for_16_and_32_character_shapes() :void {
		foreach ( [
			'JBSWY3DPEHPK3PXP',
			'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
		] as $secret ) {
			$user = \get_user_by( 'id', $this->createAdministratorUser() );
			$meta = $this->requireController()->user_metas->for( $user );
			$meta->ga_secret = $secret;
			$meta->ga_validated = true;

			new GoogleAuth( $user );

			$records = $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() );

			$this->assertCount( 1, $records );
			$this->assertSame( $secret, $records[ 0 ]->unique_id );
			$this->assertEmpty( $meta->ga_secret );
			$this->assertFalse( (bool)$meta->ga_validated );
			$this->assertProviderIsActiveForSecret( $user, $secret );
			$this->assertLoginValidationForSecret( $user, $secret );
		}
	}

	private function activateProviderAndReturnSecret( \WP_User $user ) :string {
		$provider = new GoogleAuth( $user );
		$secret = $provider->resetSecret();
		$result = $provider->activateGA( $this->calculateOtp( $secret ) );

		$this->assertTrue( $result->success, $result->error_text );

		return $secret;
	}

	private function assertLoginValidationForSecret( \WP_User $user, string $secret ) :void {
		$this->assertProviderIsActiveForSecret( $user, $secret );

		$provider = new GoogleAuth( $user );
		$this->seedLoginIntent( $user, 'fixture-ga-login-'.$user->ID );
		$this->mergeCurrentRequestTransport( [
			$provider->getLoginIntentFormParameter() => $this->calculateOtp( $secret ),
		] );

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $user )
			->run( 'fixture-ga-login-'.$user->ID );

		$this->assertSame( GoogleAuth::ProviderSlug(), $validatedSlug );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_2fa_verified_at );
	}

	private function assertProviderIsActiveForSecret( \WP_User $user, string $secret ) :void {
		$provider = new GoogleAuth( $user );
		$records = $this->loadRecordsForSlug( $user->ID, GoogleAuth::ProviderSlug() );

		$this->assertCount( 1, $records );
		$this->assertSame( $secret, $records[ 0 ]->unique_id );
		$this->assertTrue( $provider->hasValidatedProfile() );
		$this->assertArrayHasKey(
			GoogleAuth::ProviderSlug(),
			$this->requireController()->comps->mfa->getProvidersActiveForUser( $user )
		);
		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );
	}

	private function seedLoginIntent( \WP_User $user, string $plainNonce ) :void {
		$hash = \wp_hash_password( $plainNonce.$user->ID );
		$this->requireController()->user_metas->for( $user )->login_intents = [
			$hash => [
				'hash'     => $hash,
				'start'    => \time(),
				'attempts' => 0,
			],
		];
	}

	private function loadRecordsForSlug( int $userId, string $slug ) :array {
		return \array_values( \array_filter(
			$this->requireController()->db_con->mfa->getQuerySelector()->filterByUserID( $userId )->queryWithResult(),
			static fn( $record ) => $record->slug === $slug
		) );
	}

	private function calculateOtp( string $secret ) :string {
		return ( new OtpGenerator() )->calculateCode( $secret );
	}
}
