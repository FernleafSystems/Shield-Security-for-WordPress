<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class UserSessionDelete extends SecurityAdminBase {

	public const SLUG = 'user_session_delete';

	protected function exec() {
		$success = false;

		[ $userID, $uniqueID ] = \explode( '-', $this->action_data[ 'rid' ] ?? [] );

		if ( empty( $userID ) || !\is_numeric( $userID ) || $userID < 0 || empty( $uniqueID ) ) {
			$msg = __( 'Invalid session selected', 'wp-simple-firewall' );
		}
		elseif ( self::con()->comps->session->current()->shield[ 'unique' ] === $uniqueID ) {
			$msg = __( 'Please logout if you want to delete your own session.', 'wp-simple-firewall' );
		}
		else {
			self::con()->comps->session->removeSessionBasedOnUniqueID( (int)$userID, $uniqueID );
			$msg = __( 'User session deleted', 'wp-simple-firewall' );
			$success = true;
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}