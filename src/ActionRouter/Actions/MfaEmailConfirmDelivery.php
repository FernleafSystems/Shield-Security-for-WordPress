<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;

/**
 * The Security Admin vouches that this site's email arrives, which turns email 2FA on without the emailed link.
 */
class MfaEmailConfirmDelivery extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_confirm_delivery';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$con = self::con();
		$verification = new EmailDeliveryVerification();

		if ( $verification->needsVerificationAction() ) {
			$verification->markVerified();
			$con->opts->store();
			$success = true;
			$msg = __( 'Email 2FA is now on.', 'wp-simple-firewall' );
		}
		else {
			$success = false;
			$msg = __( 'Email 2FA is not waiting for verification.', 'wp-simple-firewall' );
		}

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => true,
		] )->setPayloadSuccess( $success );
	}
}
