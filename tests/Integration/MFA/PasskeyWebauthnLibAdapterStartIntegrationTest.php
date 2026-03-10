<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA\Support\{
	PasskeyFixtureLoader,
	PasskeyTestEnvironmentTrait
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PasskeyWebauthnLibAdapterStartIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_registration_start_uses_existing_records_as_exclusions() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );

		$provider = $this->createPasskeyProvider( $user );
		$challenge = $provider->startNewRegistration();

		$this->assertSame( $challenge, $this->requireController()->user_metas->for( $user )->passkeys[ 'reg_start' ] );
		$this->assertNotEmpty( $challenge[ 'challenge' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::requestHost(), $challenge[ 'rp' ][ 'id' ] ?? '' );
		$this->assertSame(
			PasskeyFixtureLoader::credentialId(),
			$challenge[ 'excludeCredentials' ][ 0 ][ 'id' ] ?? ''
		);
	}

	public function test_authentication_start_uses_existing_records_as_allowed_credentials() :void {
		$userId = $this->createAdministratorUser();
		$user = \get_user_by( 'id', $userId );
		$this->seedLegacyPasskey( $user );

		$provider = $this->createPasskeyProvider( $user );
		$challenge = $provider->startNewAuth();

		$this->assertSame( $challenge, $this->requireController()->user_metas->for( $user )->passkeys[ 'auth_challenge' ] );
		$this->assertNotEmpty( $challenge[ 'challenge' ] ?? '' );
		$this->assertSame( PasskeyFixtureLoader::requestHost(), $challenge[ 'rpId' ] ?? '' );
		$this->assertSame(
			PasskeyFixtureLoader::credentialId(),
			$challenge[ 'allowCredentials' ][ 0 ][ 'id' ] ?? ''
		);
	}
}
