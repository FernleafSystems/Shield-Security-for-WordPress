<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailSendVerification extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_send_verification';

	protected function exec() {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_LoginGuard()->getOptions();

		if ( !$opts->isEnabledEmailAuth() ) {
			$msg = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
		}
		elseif ( $opts->getIfCanSendEmailVerified() ) {
			$msg = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		else {
			$msg = __( 'Verification email resent.', 'wp-simple-firewall' );
			$opts->setOpt( 'email_can_send_verified_at', 0 );
			$this->sendEmailVerifyCanSend();
		}

		$this->response()->action_response_data = [
			'success' => true,
			'message' => $msg
		];
	}

	private function sendEmailVerifyCanSend() {
		$con = $this->getCon();
		$this->primary_mod
			->getEmailProcessor()
			->sendEmailWithWrap(
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