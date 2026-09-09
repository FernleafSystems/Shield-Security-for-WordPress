<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	EmailDeliveryVerification,
	EmailDeliveryVerificationMailer
};

class MfaEmailSendVerification extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_send_verification';

	protected function exec() {
		$con = self::con();
		$verification = new EmailDeliveryVerification();
		$status = $verification->status();
		$success = true;
		$pageReload = false;

		if ( $status === EmailDeliveryVerification::STATUS_DISABLED ) {
			$msg = __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' );
			$success = false;
		}
		elseif ( $status === EmailDeliveryVerification::STATUS_VERIFIED ) {
			$msg = __( 'Email sending has already been verified.', 'wp-simple-firewall' );
		}
		elseif ( ( new EmailDeliveryVerificationMailer() )->send() ) {
			$verification->markSent();
			$con->opts->store();
			$status = $verification->status();
			$msg = __( 'Verification email sent.', 'wp-simple-firewall' );
			$pageReload = true;
		}
		else {
			$msg = __( 'Verification email could not be sent.', 'wp-simple-firewall' );
			$success = false;
		}

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => $pageReload,
			'status'      => $status,
		] )->setPayloadSuccess( $success );
	}
}
