<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class UserSessionsTableBulkAction extends SecurityAdminBase {

	public const SLUG = 'user_sessions_bulk_action';

	protected function exec() {
		$req = Services::Request();

		$success = false;

		$IDs = $req->post( 'ids' );
		if ( empty( $IDs ) || !is_array( $IDs ) ) {
			$msg = __( 'No items selected.', 'wp-simple-firewall' );
		}
		elseif ( $req->post( 'bulk_action' ) != 'delete' ) {
			$msg = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			$sessionCon = $this->con()->getModule_Plugin()->getSessionCon();
			$yourId = $sessionCon->current()->shield[ 'unique' ] ?? '';
			$includesYourSession = false;

			foreach ( $IDs as $IDunique ) {
				[ $userID, $uniqueID ] = explode( '-', $IDunique );
				if ( $yourId === $uniqueID ) {
					$includesYourSession = true;
					continue;
				}

				$sessionCon->removeSessionBasedOnUniqueID( (int)$userID, $uniqueID );
			}

			$msg = __( 'Selected items were deleted.', 'wp-simple-firewall' );
			if ( $includesYourSession ) {
				$msg .= ' *'.__( 'Your session was retained', 'wp-simple-firewall' );
			}

			$success = true;
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}