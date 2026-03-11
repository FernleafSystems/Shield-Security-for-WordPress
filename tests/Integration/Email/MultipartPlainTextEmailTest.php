<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\AdminLoginNotification;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\BackupCodeUsed;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\FirewallBlockAlert;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts\EmailReport;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\BuildReportEmailFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\PlainTextEmailAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class MultipartPlainTextEmailTest extends ShieldIntegrationTestCase {

	use BuildReportEmailFixture;
	use LocalEmailCapture;
	use PlainTextEmailAssertions;

	public function set_up() {
		parent::set_up();
		$this->startLocalEmailCapture();
	}

	public function tear_down() {
		$this->stopLocalEmailCapture();
		parent::tear_down();
	}

	public function test_admin_email_transport_generates_alt_body_with_admin_footer() :void {
		$con = $this->requireController();
		$html = $con->action_router->render( AdminLoginNotification::class, [
			'role_name'  => 'Administrator+',
			'home_url'   => 'https://example.com',
			'username'   => 'managedadmin',
			'user_email' => 'managedadmin@example.com',
			'ip'         => '198.51.100.23',
		] );

		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Admin login test', $html ) );

		$mail = $this->lastCapturedMail();
		$this->assertSame( 'text/html', $mail[ 'content_ty' ] );
		$this->assertStringContainsString( '<html', (string)$mail[ 'html_body' ] );

		$plain = (string)$mail[ 'alt_body' ];
		$this->assertPlainTextOutputHealthy( $plain, 'Admin transport alt body' );
		$this->assertContainsTokens( $plain, [
			'Administrator+ login',
			'Details for this user are below:',
			'managedadmin@example.com',
			'Configure security email recipient',
		], 'Admin transport alt body' );
		$this->assertTokensAppearInOrder( $plain, [
			'Details for this user are below:',
			'Site URL: https://example.com',
			'Username: managedadmin',
			'Email: managedadmin@example.com',
			'IP Address: 198.51.100.23',
		], 'Admin transport alt body order' );
	}

	public function test_user_style_email_transport_preserves_user_footer_contract() :void {
		$con = $this->requireController();
		$html = $con->action_router->render( BackupCodeUsed::class, [
			'home_url' => 'https://example.com',
			'username' => 'mfauser',
			'ip'       => '203.0.113.87',
		] );

		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Backup code test', $html ) );

		$plain = (string)$this->lastCapturedMail()[ 'alt_body' ];
		$this->assertPlainTextOutputHealthy( $plain, 'User transport alt body' );
		$this->assertContainsTokens( $plain, [
			'Backup Login code was just used',
			'Login Details',
			'URL: https://example.com',
			'Username: mfauser',
			'IP Address: 203.0.113.87',
		], 'User transport alt body' );
		$this->assertStringNotContainsString( 'Configure security email recipient', $plain );
	}

	public function test_table_heavy_email_transport_generates_readable_alt_body() :void {
		$con = $this->requireController();
		$con->this_req->path = '/wp-login.php';

		$html = $con->action_router->render( FirewallBlockAlert::class, [
			'ip'         => '203.0.113.10',
			'block_meta' => [
				'firewall_rule_name'  => 'SQL Injection',
				'match_pattern'       => 'select%20from',
				'match_request_param' => 'query',
				'match_request_value' => 'select from wp_users',
			],
		] );

		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Firewall block test', $html ) );

		$plain = (string)$this->lastCapturedMail()[ 'alt_body' ];
		$this->assertPlainTextOutputHealthy( $plain, 'Firewall transport alt body' );
		$this->assertContainsTokens( $plain, [
			'Request Details:',
			'Visitor IP Address',
			'SQL Injection',
			'Configure Firewall Block alert emails',
			'IP Address Lookup',
		], 'Firewall transport alt body' );
		$this->assertTokensAppearInOrder( $plain, [
			'Request Details:',
			'Visitor IP Address',
			'Firewall Rule',
			'Firewall Pattern',
			'Request Path',
			'Parameter Name',
			'Parameter Value',
		], 'Firewall request detail order' );
	}

	public function test_report_email_transport_generates_alt_body_from_real_report_render() :void {
		$con = $this->requireController();
		$report = $this->buildReportFixture( Constants::REPORT_TYPE_ALERT );
		$html = $con->action_router->render( EmailReport::class, [
			'home_url'     => 'https://example.com',
			'reports'      => [ $report ],
			'detail_level' => 'detailed',
		] );

		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Report transport test', $html ) );

		$plain = (string)$this->lastCapturedMail()[ 'alt_body' ];
		$this->assertPlainTextOutputHealthy( $plain, 'Report transport alt body' );
		$this->assertContainsTokens( $plain, [
			'Security Report',
			'Latest Scan Results',
			'Scan File Repairs',
			'Statistics',
			'Changes',
			'View Full Report',
			'Generated by Shield Security',
		], 'Report transport alt body' );
		$this->assertTokensAppearInOrder( $plain, [
			'Security Report',
			'Latest Scan Results',
			'Malware Scan',
			'... and 2 more',
			'Scan File Repairs',
			'Automatic Repairs',
			'Statistics',
			'Security Stats',
			'IP Offenses',
			'Login Failures',
			'Changes',
			'Plugins',
			'Users',
			'View Full Report',
		], 'Report section order' );
	}

	public function test_send_vo_prefers_explicit_plain_text_alt_body() :void {
		$con = $this->requireController();
		$plainText = "Plain override line 1.\nPlain override line 2.";

		$con->email_con->sendVO(
			EmailVO::Factory(
				'recipient@example.com',
				'Explicit plain text test',
				'<html><body><p>HTML body that should not be used.</p></body></html>',
				$plainText
			)
		);

		$this->assertSame( $plainText, (string)$this->lastCapturedMail()[ 'alt_body' ] );
	}

	public function test_send_vo_does_not_leave_phpmailer_alt_body_hook_active() :void {
		$con = $this->requireController();
		$html = $con->action_router->render( AdminLoginNotification::class, [
			'role_name'  => 'Administrator+',
			'home_url'   => 'https://example.com',
			'username'   => 'managedadmin',
			'user_email' => 'managedadmin@example.com',
			'ip'         => '198.51.100.23',
		] );

		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Scoped hook test', $html ) );

		$unrelatedMailer = (object)[
			'Body'        => '<html><body><p>Outside Shield email path.</p></body></html>',
			'AltBody'     => '',
			'ContentType' => 'text/html',
		];
		\do_action( 'phpmailer_init', $unrelatedMailer );

		$this->assertSame( '', (string)$unrelatedMailer->AltBody, 'EmailCon phpmailer_init hook should be removed after send.' );
	}

	public function test_back_to_back_sends_do_not_reuse_previous_alt_body() :void {
		$con = $this->requireController();
		$con->email_con->sendVO(
			EmailVO::Factory(
				'recipient@example.com',
				'First explicit test',
				'<html><body><p>Ignored HTML</p></body></html>',
				"Explicit body A\nLine 2"
			)
		);

		$report = $this->buildReportFixture( Constants::REPORT_TYPE_INFO );
		$html = $con->action_router->render( EmailReport::class, [
			'home_url'     => 'https://example.com',
			'reports'      => [ $report ],
			'detail_level' => 'detailed',
		] );
		$con->email_con->sendVO( EmailVO::Factory( 'recipient@example.com', 'Second generated test', $html ) );

		$mails = $this->capturedMails();
		$this->assertCount( 2, $mails );
		$this->assertSame( "Explicit body A\nLine 2", (string)$mails[ 0 ][ 'alt_body' ] );
		$this->assertNotSame( (string)$mails[ 0 ][ 'alt_body' ], (string)$mails[ 1 ][ 'alt_body' ] );
		$this->assertStringContainsString( 'Security Report', (string)$mails[ 1 ][ 'alt_body' ] );
	}
}
