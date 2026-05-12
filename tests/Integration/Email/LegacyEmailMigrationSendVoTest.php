<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaEmailSendVerification;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\LicenseEmails;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\UserSessionHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class LegacyEmailMigrationSendVoTest extends ShieldIntegrationTestCase {

	/**
	 * @var array<int, array>
	 */
	private array $mails = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities( [ 'instant_alerts' ] );
		$this->mails = [];
		add_filter( 'pre_wp_mail', [ $this, 'captureWpMail' ], 10, 2 );
	}

	public function tear_down() {
		remove_filter( 'pre_wp_mail', [ $this, 'captureWpMail' ], 10 );
		$this->mails = [];
		parent::tear_down();
	}

	/**
	 * Intercept outgoing email so tests can validate message payloads.
	 * @param mixed $pre
	 */
	public function captureWpMail( $pre, array $atts ) :bool {
		$this->mails[] = $atts;
		return true;
	}

	public function testMfaEmailVerificationMessageIsSent() :void {
		$con = $this->requireController();
		$this->loginAsSecurityAdmin( [
			'role'       => 'administrator',
			'user_login' => 'secadmin',
			'user_email' => 'secadmin@example.com',
		] );
		$con->opts
			->optSet( 'enable_email_authentication', 'Y' )
			->optSet( 'email_can_send_verified_at', 0 );

		$con->action_router->action( MfaEmailSendVerification::class );

		$mail = $this->lastMail();
		$this->assertStringContainsString( 'Email Sending Verification', (string)( $mail[ 'subject' ] ?? '' ) );
		$this->assertStringContainsString( 'Click the verify link:', (string)( $mail[ 'message' ] ?? '' ) );
	}

	public function testLicenseWarningEmailRespectsThrottle() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'block_send_email_address', 'lic-warning@example.com' )
			->optSet( 'last_warning_email_sent_at', 0 );

		$emails = new LicenseEmails();
		$emails->sendLicenseWarningEmail();
		$this->assertCount( 1, $this->mails );
		$first = $this->lastMail();
		$this->assertStringContainsString( 'Pro License Check Has Failed', (string)( $first[ 'subject' ] ?? '' ) );

		$emails->sendLicenseWarningEmail();
		$this->assertCount( 1, $this->mails, 'Second warning email should be throttled.' );
	}

	public function testLicenseDeactivatedEmailRespectsThrottle() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'block_send_email_address', 'lic-deactivated@example.com' )
			->optSet( 'last_deactivated_email_sent_at', 0 );

		$emails = new LicenseEmails();
		$emails->sendLicenseDeactivatedEmail();
		$this->assertCount( 1, $this->mails );
		$first = $this->lastMail();
		$this->assertStringContainsString( '[Action May Be Required] Pro License Has Been Deactivated', (string)( $first[ 'subject' ] ?? '' ) );

		$emails->sendLicenseDeactivatedEmail();
		$this->assertCount( 1, $this->mails, 'Second deactivated email should be throttled.' );
	}

	public function testAdminLoginInstantAlertEmailIsSentToReportRecipientWithExpectedDetails() :void {
		$con = $this->requireController();
		$con->this_req->ip = '198.51.100.23';
		$con->opts
			->optSet( 'instant_alert_admin_login', 'email' )
			->optSet( 'block_send_email_address', 'admin-notify@example.com' );
		$this->resetInstantAlertsCache();

		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'managedadmin-notify',
			'user_email' => 'managedadmin-notify@example.com',
		] );
		$this->assertIsInt( $userId );
		$user = get_user_by( 'id', $userId );
		$this->assertInstanceOf( \WP_User::class, $user );

		( new AlertHandlerAdminLogin() )->execute();

		do_action( 'wp_login', $user->user_login, $user );

		$mail = $this->lastMail();
		$this->assertStringContainsString( 'Alert: Admin Login Detected', (string)( $mail[ 'subject' ] ?? '' ) );
		$this->assertStringContainsString( 'successful Administrator+ login', (string)( $mail[ 'message' ] ?? '' ) );
		$this->assertStringContainsString( 'Login Details', (string)( $mail[ 'message' ] ?? '' ) );
		$this->assertStringContainsString( 'managedadmin-notify@example.com', (string)( $mail[ 'message' ] ?? '' ) );
		$this->assertStringContainsString( 'admin-notify@example.com', (string)( $mail[ 'to' ] ?? '' ) );
		$this->assertStringContainsString( 'Configure security email recipient', (string)( $mail[ 'message' ] ?? '' ) );
	}

	public function testAdminLoginInstantAlertSuppressesDuplicateUserLoginNoticeForSameRecipient() :void {
		$con = $this->requireController();
		$con->this_req->ip = '198.51.100.23';
		$con->opts
			->optSet( 'instant_alert_admin_login', 'email' )
			->optSet( 'enable_user_login_email_notification', 'Y' )
			->optSet( 'block_send_email_address', 'managedadmin-duplicate@example.com' );
		$this->resetInstantAlertsCache();

		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'managedadmin-duplicate',
			'user_email' => 'managedadmin-duplicate@example.com',
		] );
		$this->assertIsInt( $userId );
		$user = get_user_by( 'id', $userId );
		$this->assertInstanceOf( \WP_User::class, $user );

		( new AlertHandlerAdminLogin() )->execute();
		( new UserSessionHandler() )->execute();

		do_action( 'wp_login', $user->user_login, $user );

		$this->assertCount( 1, $this->mails );
		$this->assertStringContainsString( 'Alert: Admin Login Detected', (string)( $this->lastMail()[ 'subject' ] ?? '' ) );
	}

	public function testBackupCodeUsedEmailIsUserStyleFooter() :void {
		$con = $this->requireController();
		$con->this_req->ip = '203.0.113.87';

		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'mfauser',
			'user_email' => 'mfauser@example.com',
		] );
		$user = get_user_by( 'id', $userId );

		$provider = new BackupCodes( $user );
		$provider->postSuccessActions();

		$mail = $this->lastMail();
		$this->assertStringContainsString( 'Backup Login Code Just Used', (string)( $mail[ 'subject' ] ?? '' ) );
		$this->assertStringContainsString( 'Login Details', (string)( $mail[ 'message' ] ?? '' ) );
		$this->assertStringNotContainsString( 'Configure security email recipient', (string)( $mail[ 'message' ] ?? '' ) );
	}

	private function lastMail() :array {
		$this->assertNotEmpty( $this->mails, 'Expected at least one captured email.' );
		return $this->mails[ \count( $this->mails ) - 1 ];
	}

	private function resetInstantAlertsCache() :void {
		$alertsProperty = new \ReflectionProperty( $this->requireController()->comps->instant_alerts, 'alerts' );
		$alertsProperty->setAccessible( true );
		$alertsProperty->setValue( $this->requireController()->comps->instant_alerts, null );
	}
}
