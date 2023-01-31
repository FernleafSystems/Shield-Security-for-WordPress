<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;

class MfaRemoveAll extends MfaBase {

	/** Attempting to remove MFA settings on another user account. */
	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'mfa_profile_remove_all';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$userID = $this->action_data[ 'user_id' ] ?? null;

		if ( !$this->getCon()->isPluginAdmin() ) {
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
			$result = $mod->getMfaController()->removeAllFactorsForUser( (int)$userID );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
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