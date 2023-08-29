<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class UserSessionDelete extends SecurityAdminBase {

	public const SLUG = 'user_session_delete';

	protected function exec() {
		$sessionCon = self::con()->getModule_Plugin()->getSessionCon();
		$success = false;

		[ $userID, $uniqueID ] = \explode( '-', Services::Request()->post( 'rid', '' ) );

		if ( empty( $userID ) || !\is_numeric( $userID ) || $userID < 0 || empty( $uniqueID ) ) {
			$msg = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( $sessionCon->current()->shield[ 'unique' ] === $uniqueID ) {
			$msg = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else {
			$sessionCon->removeSessionBasedOnUniqueID( (int)$userID, $uniqueID );
			$msg = __( 'User session deleted', 'wp-simple-firewall' );
			$success = true;
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}