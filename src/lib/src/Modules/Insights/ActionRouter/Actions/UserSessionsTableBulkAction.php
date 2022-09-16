<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionsTableBulkAction extends SecurityAdminBase {

	const SLUG = 'user_sessions_bulk_action';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
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
			$sessionCon = $this->getCon()->getModule_Sessions()->getSessionCon();
			$yourId = $mod->getSessionWP()->shield[ 'unique' ] ?? '';
			$includesYourSession = false;

			foreach ( $IDs as $IDunique ) {
				list( $userID, $uniqueID ) = explode( '-', $IDunique );
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