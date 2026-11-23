<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	EmailDeliveryVerification,
	EmailDeliveryVerificationDialog,
	EmailDeliveryVerificationMailer
};

/**
 * Sends the verification email from the email 2FA verification dialog. When there's nothing left to verify (it was
 * verified or turned off elsewhere), the page reloads so the dialog goes; otherwise the dialog shows the new delivery.
 */
class MfaEmailSendVerification extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_send_verification';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$con = self::con();
		$verification = new EmailDeliveryVerification();
		$status = $verification->status();

		if ( $status === EmailDeliveryVerification::STATUS_DISABLED ) {
			$this->response()->setPayload( [
				'message'     => __( 'Email 2FA option is not currently enabled.', 'wp-simple-firewall' ),
				'page_reload' => true,
			] )->setPayloadSuccess( false );
		}
		elseif ( $status === EmailDeliveryVerification::STATUS_VERIFIED ) {
			$this->response()->setPayload( [
				'message'     => __( 'Email sending has already been verified.', 'wp-simple-firewall' ),
				'page_reload' => true,
			] )->setPayloadSuccess( true );
		}
		else {
			$sent = ( new EmailDeliveryVerificationMailer() )->send();
			if ( $sent ) {
				$con->opts->store();
			}
			$this->response()->setPayload( [
				'message'     => $sent ? __( 'Verification email sent.', 'wp-simple-firewall' )
					: __( 'Verification email could not be sent.', 'wp-simple-firewall' ),
				'page_reload' => false,
				'delivery'    => ( new EmailDeliveryVerificationDialog() )->delivery( !$sent ),
			] )->setPayloadSuccess( $sent );
		}
	}
}
