<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionDelete extends SecurityAdminBase {

	const SLUG = 'user_session_delete';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$success = false;

		list( $userID, $uniqueID ) = explode( '-', Services::Request()->post( 'rid', '' ) );

		if ( empty( $userID ) || !is_numeric( $userID ) || $userID < 0 || empty( $uniqueID ) ) {
			$msg = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( $mod->getSessionWP()->shield[ 'unique' ] === $uniqueID ) {
			$msg = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else {
			$con->getModule_Sessions()
				->getSessionCon()
				->removeSessionBasedOnUniqueID( (int)$userID, $uniqueID );
			$msg = __( 'User session deleted', 'wp-simple-firewall' );
			$success = true;
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}