<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class MfaRemoveAll extends MfaUserConfigBase {

	/** Attempting to remove MFA settings on another user account. */
	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mfa_profile_remove_all';

	protected function exec() {
		$userID = $this->action_data[ 'user_id' ] ?? null;

		if ( !self::con()->isPluginAdmin() ) {
			$response = [
				'success' => false,
				'message' => __( 'Removal of MFA factors for a user must be done by the Security Admin', 'wp-simple-firewall' ),
			];
		}
		elseif ( empty( $userID ) ) {
			$response = [
				'success' => false,
				'message' => 'Invalid request with no User ID',
			];
		}
		else {
			$result = self::con()->comps->mfa->removeAllFactorsForUser( (int)$userID );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true,
			];
		}

		$this->response()->action_response_data = $response;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id'
		];
	}
}