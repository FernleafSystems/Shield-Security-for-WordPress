<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailSendVerification extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_send_verification';

	protected function exec() {
		$opts = self::con()->opts;
		if ( !$opts->optIs( 'enable_email_authentication', 'Y' ) ) {
			$msg = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
		}
		elseif ( $opts->optGet( 'email_can_send_verified_at' ) > 0 ) {
			$msg = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$opts->optSet( 'email_can_send_verified_at', 0 );
			$this->sendEmailVerifyCanSend();
			$msg = __( 'Verification email resent.', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success' => true,
			'message' => $msg
		];
	}

	private function sendEmailVerifyCanSend() {
		$con = self::con();
		$con->email_con->sendEmailWithWrap(
			Services::WpGeneral()->getSiteAdminEmail(), //TODO: $this->getPluginReportEmail()?
			__( 'Email Sending Verification', 'wp-simple-firewall' ),
			[
				__( 'Before enabling 2-factor email authentication for your WordPress site, you must verify you can receive this email.', 'wp-simple-firewall' ),
				__( 'This verifies your website can send email and that your account can receive emails sent from your site.', 'wp-simple-firewall' ),
				'',
				sprintf(
					__( 'Click the verify link: %s', 'wp-simple-firewall' ),
					$con->plugin_urls->noncedPluginAction( MfaCanEmailSendVerify::class, $con->plugin_urls->adminHome() )
				)
			]
		);
	}
}