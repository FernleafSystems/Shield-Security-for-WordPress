<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaRemoveAll extends MfaBase {

	/** Attempting to remove MFA settings on another user account. */
	use Traits\SecurityAdminRequired;

	public const SLUG = 'mfa_profile_remove_all';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$userID = Services::Request()->post( 'user_id' );
		if ( !empty( $userID ) ) {
			$result = $mod->getMfaController()->removeAllFactorsForUser( (int)$userID );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}
		else {
			$response = [
				'success' => false,
				'message' => 'Invalid request with no User ID',
			];
		}

		$this->response()->action_response_data = $response;
	}
}