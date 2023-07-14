<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\Delete;
use FernleafSystems\Wordpress\Services\Services;

class AdminNoteBulkAction extends BaseAction {

	public const SLUG = 'admin_note_bulk_action';

	protected function exec() {
		$success = false;

		$IDs = Services::Request()->post( 'ids' );
		if ( empty( $IDs ) || !is_array( $IDs ) ) {
			$msg = __( 'No items selected.', 'wp-simple-firewall' );
		}
		elseif ( Services::Request()->post( 'bulk_action' ) != 'delete' ) {
			$msg = __( 'Not a supported action.', 'wp-simple-firewall' );
		}
		else {
			/** @var Delete $deleter */
			$deleter = $this->con()->getModule_Plugin()->getDbHandler_Notes()->getQueryDeleter();
			foreach ( $IDs as $id ) {
				if ( is_numeric( $id ) ) {
					$deleter->deleteById( $id );
				}
			}
			$success = true;
			$msg = __( 'Selected items were deleted.', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}