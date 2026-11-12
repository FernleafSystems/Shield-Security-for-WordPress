<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;

class MfaCanEmailSendVerify extends MfaUserConfigBase {

	public const SLUG = 'email_send_verify';
	public const FIELD_TOKEN = 'verify_token';

	/**
	 * The link is emailed to the report address, which may belong to a different user than the admin who
	 * triggered it, so a user-bound action nonce cannot prove intent here. The link's own token does.
	 */
	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONE,
		];
	}

	protected function exec() {
		$con = self::con();
		$verification = new EmailDeliveryVerification();

		if ( $verification->verifyLinkToken( $this->fields()->string( self::FIELD_TOKEN, '' ) ) ) {
			$verification->markVerified();
			$con->opts->store();
			$success = true;
			$msg = __( 'Email verification completed successfully.', 'wp-simple-firewall' );
		}
		else {
			$success = false;
			$msg = __( 'This email verification link is not valid. Please use the link in the most recent verification email.', 'wp-simple-firewall' );
		}

		$con->admin_notices->addFlash( $msg, $this->getActiveWPUser(), !$success );

		$this->response()->setPayload( [
			'message'  => $msg,
			'redirect' => $con->plugin_urls->adminHome()
		] )->setPayloadSuccess( $success );
	}
}
