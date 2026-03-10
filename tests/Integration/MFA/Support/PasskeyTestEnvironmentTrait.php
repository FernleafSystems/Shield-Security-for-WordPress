<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

trait PasskeyTestEnvironmentTrait {

	use CurrentRequestFixture;

	private array $passkeyRequestSnapshot = [];

	private string $passkeyOriginalHomeUrl = '';

	private string $passkeyOriginalSiteUrl = '';

	protected function setUpPasskeyEnvironment() :void {
		$this->passkeyRequestSnapshot = $this->snapshotCurrentRequestState();
		$this->passkeyOriginalHomeUrl = (string)\get_option( 'home' );
		$this->passkeyOriginalSiteUrl = (string)\get_option( 'siteurl' );

		$fixtureUrl = PasskeyFixtureLoader::requestScheme().'://'.PasskeyFixtureLoader::requestHost();
		\update_option( 'home', $fixtureUrl );
		\update_option( 'siteurl', $fixtureUrl );

		$this->requireController()->opts->optSet( 'enable_passkeys', 'Y' );
		$this->applyCurrentRequestState( [
			'HTTP_HOST'      => PasskeyFixtureLoader::requestHost(),
			'HTTPS'          => 'on',
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
	}

	protected function tearDownPasskeyEnvironment() :void {
		\update_option( 'home', $this->passkeyOriginalHomeUrl );
		\update_option( 'siteurl', $this->passkeyOriginalSiteUrl );
		$this->restoreCurrentRequestState( $this->passkeyRequestSnapshot );
	}

	protected function createPasskeyProvider( \WP_User $user ) :Passkey {
		return new Passkey( $user );
	}

	protected function seedLegacyPasskey( \WP_User $user, string $label = 'Fixture Passkey' ) :int {
		$this->mergePasskeyMeta( $user, [
			'user_key' => PasskeyFixtureLoader::userHandleRaw(),
		] );

		return TestDataFactory::insertMfaRecord(
			$user->ID,
			Passkey::ProviderSlug(),
			PasskeyFixtureLoader::legacyRecord(),
			[
				'unique_id'    => PasskeyFixtureLoader::credentialUniqueId(),
				'label'        => $label,
				'passwordless' => true,
			]
		);
	}

	protected function seedPasskeyRegistrationOptions( \WP_User $user, ?array $options = null ) :void {
		$this->mergePasskeyMeta( $user, [
			'user_key'  => PasskeyFixtureLoader::userHandleRaw(),
			'reg_start' => $options ?? PasskeyFixtureLoader::registrationOptions(),
		] );
	}

	protected function seedPasskeyAuthenticationOptions( \WP_User $user, ?array $options = null ) :void {
		$this->mergePasskeyMeta( $user, [
			'user_key'       => PasskeyFixtureLoader::userHandleRaw(),
			'auth_challenge' => $options ?? PasskeyFixtureLoader::authenticationOptions(),
		] );
	}

	protected function seedLoginIntent( \WP_User $user, string $plainNonce ) :void {
		$hash = \wp_hash_password( $plainNonce.$user->ID );
		$this->requireController()->user_metas->for( $user )->login_intents = [
			$hash => [
				'hash'     => $hash,
				'start'    => \time(),
				'attempts' => 0,
			],
		];
	}

	protected function setPasskeyLoginOtpRequest( Passkey $provider, string $rawResponseJson ) :void {
		$this->mergeCurrentRequestTransport( [
			$provider->getLoginIntentFormParameter() => \base64_encode( $rawResponseJson ),
		] );
	}

	protected function insertLegacyPasskeyActionRecord( int $userId, string $label = 'Fixture Passkey' ) :int {
		return TestDataFactory::insertMfaRecord(
			$userId,
			Passkey::ProviderSlug(),
			PasskeyFixtureLoader::legacyRecord(),
			[
				'unique_id'    => PasskeyFixtureLoader::credentialUniqueId(),
				'label'        => $label,
				'passwordless' => true,
			]
		);
	}

	private function mergePasskeyMeta( \WP_User $user, array $data ) :void {
		$meta = $this->requireController()->user_metas->for( $user );
		$meta->passkeys = \array_merge(
			\is_array( $meta->passkeys ) ? $meta->passkeys : [],
			$data
		);
	}
}
