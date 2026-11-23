<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;

/**
 * Only an administrator can use the link. Security Admin isn't required, because the link goes to the report address,
 * which may belong to an administrator other than the Security Admin. Anyone else who opens it changes nothing, and
 * the link keeps working.
 */
class MfaCanEmailSendVerify extends BaseAction {

	use Traits\SecurityAdminNotRequired;

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

	/**
	 * A link that no longer verifies does nothing: it was already used (such as on a page reload), or a newer send
	 * replaced it, or it expired. While delivery is still unverified, the verification dialog on the page says where
	 * the latest send stands.
	 */
	protected function exec() {
		$con = self::con();
		$verification = new EmailDeliveryVerification();

		$verified = $verification->verifyLinkToken( $this->fields()->string( self::FIELD_TOKEN, '' ) );
		if ( $verified ) {
			$verification->markVerified();
			$con->opts->store();
			$con->this_req->email_delivery_verified_by_link = true;
		}

		$this->response()->setPayloadSuccess( $verified );
	}
}
