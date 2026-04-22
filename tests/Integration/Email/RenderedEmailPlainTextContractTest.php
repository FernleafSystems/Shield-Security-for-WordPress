<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\BackupCodeUsed;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertFirewallBlock;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts\{
	EmailReportAlert,
	EmailReportInfo
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\BuildReportEmailFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\PlainTextEmailAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ConvertHtmlToText;

class RenderedEmailPlainTextContractTest extends ShieldIntegrationTestCase {

	use BuildReportEmailFixture;
	use PlainTextEmailAssertions;

	public function test_admin_login_notification_render_converts_cleanly() :void {
		$html = $this->requireController()->action_router->render( EmailInstantAlertAdminLogin::class, [
			'alert_data' => [
				'admin_login' => [
					'role_name'  => 'Administrator+',
					'username'   => 'managedadmin',
					'user_email' => 'managedadmin@example.com',
					'ip'         => '198.51.100.23',
				]
			],
		] );

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertPlainTextOutputHealthy( $text, 'Admin render conversion' );
		$this->assertNotSame( '', \trim( $text ) );
	}

	public function test_backup_code_used_render_converts_cleanly_with_user_footer() :void {
		$html = $this->requireController()->action_router->render( BackupCodeUsed::class, [
			'home_url' => 'https://example.com',
			'username' => 'mfauser',
			'ip'       => '203.0.113.87',
		] );

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertPlainTextOutputHealthy( $text, 'Backup code render conversion' );
		$this->assertNotSame( '', \trim( $text ) );
	}

	public function test_firewall_block_alert_render_converts_request_details_and_links() :void {
		$html = $this->requireController()->action_router->render( EmailInstantAlertFirewallBlock::class, [
			'alert_data' => [
				'firewall_block' => [
					'ip'                  => '203.0.113.10',
					'request_path'        => '/wp-login.php',
					'firewall_rule_name'  => 'SQL Injection',
					'match_pattern'       => 'select%20from',
					'match_request_param' => 'query',
					'match_request_value' => 'select from wp_users',
				],
			],
		] );

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertPlainTextOutputHealthy( $text, 'Firewall render conversion' );
		$this->assertNotSame( '', \trim( $text ) );
	}

	public function test_alert_report_render_converts_real_report_html_with_alert_only_contracts() :void {
		$html = $this->requireController()->action_router->render( EmailReportAlert::class, [
			'home_url'     => 'https://example.com',
			'report'       => $this->buildReportFixture( Constants::REPORT_TYPE_ALERT ),
			'detail_level' => 'detailed',
		] );

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertPlainTextOutputHealthy( $text, 'Alert report render conversion' );
		$this->assertNotSame( '', \trim( $text ) );
	}

	public function test_info_report_render_converts_real_report_html_with_status_headline() :void {
		$html = $this->requireController()->action_router->render( EmailReportInfo::class, [
			'home_url'     => 'https://example.com',
			'report'       => $this->buildReportFixture( Constants::REPORT_TYPE_INFO ),
			'detail_level' => 'detailed',
		] );

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertPlainTextOutputHealthy( $text, 'Info report render conversion' );
		$this->assertNotSame( '', \trim( $text ) );
	}
}
