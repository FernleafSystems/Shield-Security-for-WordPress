<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\MFA;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	LoginRequestCapture,
	MfaSkip
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class MfaRememberMeIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	private array $rememberAgent = [
		'ip'         => '198.51.100.25',
		'user_agent' => 'remember-agent-a',
	];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->enablePremiumCapabilities( [ '2fa_remember_me' ] );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'enable_google_authenticator',
			'mfa_skip',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator' => 'Y',
			'mfa_skip'                    => 1,
		], true );
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		\add_filter( 'shield/2fa_remember_me_params', [ $this, 'rememberAgentParams' ] );
	}

	public function tear_down() :void {
		\remove_filter( 'shield/2fa_remember_me_params', [ $this, 'rememberAgentParams' ] );
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			$this->resetMfaProviderCache();
		}
		parent::tear_down();
	}

	public function test_remember_me_hash_is_keyed_by_ip_and_user_agent() :void {
		$user = $this->createMfaUser();
		$skip = new MfaSkip();

		$skip->addMfaSkip( $user );
		$hashes = $this->requireController()->user_metas->for( $user )->hash_loginmfa;

		$this->assertIsArray( $hashes );
		$this->assertCount( 1, $hashes );
		$this->assertTrue( $skip->canMfaSkip( $user ) );

		$this->rememberAgent[ 'user_agent' ] = 'remember-agent-b';
		$this->assertFalse( $skip->canMfaSkip( $user ) );

		$this->rememberAgent[ 'user_agent' ] = 'remember-agent-a';
		$this->rememberAgent[ 'ip' ] = '198.51.100.26';
		$this->assertFalse( $skip->canMfaSkip( $user ) );
	}

	public function test_expired_and_disabled_remember_me_hashes_do_not_skip_mfa() :void {
		$user = $this->createMfaUser();
		$skip = new MfaSkip();
		$skip->addMfaSkip( $user );

		$hashes = $this->requireController()->user_metas->for( $user )->hash_loginmfa;
		$agentHash = (string)\array_key_first( $hashes );
		$hashes[ $agentHash ] = \time() - \DAY_IN_SECONDS - 60;
		$this->requireController()->user_metas->for( $user )->hash_loginmfa = $hashes;

		$this->assertFalse( $skip->canMfaSkip( $user ) );

		$skip->addMfaSkip( $user );
		RuntimeTestState::restoreOptions( [
			'mfa_skip' => 0,
		], true );

		$this->assertFalse( $skip->canMfaSkip( $user ) );
	}

	public function test_valid_remember_me_skip_suppresses_new_login_intent_capture() :void {
		$user = $this->createMfaUser();
		( new MfaSkip() )->addMfaSkip( $user );

		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );
		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );

		$method = new \ReflectionMethod( LoginRequestCapture::class, 'captureLogin' );
		$method->setAccessible( true );
		$method->invoke( new LoginRequestCapture(), $user );

		$this->assertSame( [], $this->requireController()->user_metas->for( $user )->login_intents );
	}

	public function rememberAgentParams() :array {
		return $this->rememberAgent;
	}

	private function createMfaUser() :\WP_User {
		$user = \get_user_by( 'id', $this->createAdministratorUser() );
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'unique_id' => 'JBSWY3DPEHPK3PXP',
			'label'     => 'Fixture GA',
		] );
		$this->resetMfaProviderCache();
		return $user;
	}

	private function resetMfaProviderCache() :void {
		$ref = new \ReflectionClass( $this->requireController()->comps->mfa );
		if ( $ref->hasProperty( 'providers' ) ) {
			$prop = $ref->getProperty( 'providers' );
			$prop->setAccessible( true );
			$prop->setValue( $this->requireController()->comps->mfa, [] );
		}
	}
}
