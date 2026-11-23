<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class MfaRemoveAll extends MfaUserConfigBase {

	/** Attempting to remove MFA settings on another user account. */
	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mfa_profile_remove_all';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$userID = $this->fields()->positiveInt( 'user_id', 0 );

		if ( !self::con()->isPluginAdmin() ) {
			$response = [
				'success' => false,
				'message' => __( 'Removal of MFA factors for a user must be done by the Security Admin', 'wp-simple-firewall' ),
			];
		}
		elseif ( empty( $userID ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Invalid request with no User ID', 'wp-simple-firewall' ),
			];
		}
		else {
			$result = self::con()->comps->mfa->removeAllFactorsForUser( $userID );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true,
			];
		}

		$payloadSuccess = $response[ 'success' ];
		unset( $response[ 'success' ] );

		$this->response()
			 ->setPayload( $response )
			 ->setPayloadSuccess( $payloadSuccess );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id'
		];
	}
}
