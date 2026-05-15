<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\UserSessionHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class UserLoginNotificationEmailIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() :void {
		parent::set_up();
		$this->requireDb( 'mfa' );
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'block_send_email_address',
			'enable_google_authenticator',
			'enable_user_login_email_notification',
			'instant_alert_admin_login',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->applyCurrentRequestState( [
			'REMOTE_ADDR'    => '198.51.100.44',
			'REQUEST_METHOD' => 'POST',
			'REQUEST_URI'    => '/wp-login.php',
		] );
		$this->startLocalEmailCapture();
	}

	public function tear_down() :void {
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->restoreCurrentRequestState( $this->requestSnapshot );
			RuntimeTestState::resetMfaProviderCache();
		}
		parent::tear_down();
	}

	public function test_enabled_login_notification_sends_one_mail_to_logging_in_user() :void {
		$user = $this->createUserWithEmail( 'login-notice-enabled@example.test' );
		RuntimeTestState::restoreOptions( [
			'enable_user_login_email_notification' => 'Y',
			'instant_alert_admin_login'            => 'disabled',
		], true );

		$this->captureLoginForUser( $user );

		$this->assertCount( 1, $this->capturedMails() );
		$this->assertSame( [ $user->user_email ], (array)( $this->lastCapturedMail()[ 'to' ] ?? [] ) );
		$this->assertGreaterThan( 0, (int)$this->requireController()->user_metas->for( $user )->record->last_login_at );
	}

	public function test_disabled_login_notification_sends_no_mail() :void {
		$user = $this->createUserWithEmail( 'login-notice-disabled@example.test' );
		RuntimeTestState::restoreOptions( [
			'enable_user_login_email_notification' => 'N',
			'instant_alert_admin_login'            => 'disabled',
		], true );

		$this->captureLoginForUser( $user );

		$this->assertCount( 0, $this->capturedMails() );
	}

	public function test_mfa_login_intent_subject_suppresses_user_login_notice() :void {
		$user = $this->createUserWithEmail( 'login-notice-mfa@example.test' );
		RuntimeTestState::restoreOptions( [
			'enable_google_authenticator'          => 'Y',
			'enable_user_login_email_notification' => 'Y',
			'instant_alert_admin_login'            => 'disabled',
		], true );
		TestDataFactory::insertMfaRecord( $user->ID, GoogleAuth::ProviderSlug(), [], [
			'label'     => 'Login Notice GA',
			'unique_id' => 'JBSWY3DPEHPK3PXP',
		] );
		RuntimeTestState::resetMfaProviderCache();

		$this->assertTrue( $this->requireController()->comps->mfa->isSubjectToLoginIntent( $user ) );

		$this->captureLoginForUser( $user );

		$this->assertCount( 0, $this->capturedMails() );
	}

	public function test_admin_alert_duplicate_recipient_suppresses_user_login_notice() :void {
		$user = $this->createUserWithEmail( 'login-notice-duplicate@example.test' );
		RuntimeTestState::restoreOptions( [
			'block_send_email_address'             => $user->user_email,
			'enable_user_login_email_notification' => 'Y',
			'instant_alert_admin_login'            => 'email',
		], true );

		$this->captureLoginForUser( $user );

		$this->assertCount( 0, $this->capturedMails() );
	}

	private function createUserWithEmail( string $email ) :\WP_User {
		$user = \get_user_by( 'id', $this->createAdministratorUser( [
			'user_email' => $email,
		] ) );
		$this->assertInstanceOf( \WP_User::class, $user );
		return $user;
	}

	private function captureLoginForUser( \WP_User $user ) :void {
		( new UserSessionHandler() )->onWpLogin( $user->user_login, $user );
	}
}
