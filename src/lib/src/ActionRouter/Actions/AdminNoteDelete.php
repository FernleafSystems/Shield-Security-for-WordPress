<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class AdminNoteDelete extends BaseAction {

	public const SLUG = 'admin_note_delete';

	protected function exec() {
		$resp = $this->response();

		$noteID = Services::Request()->post( 'rid' );
		if ( empty( $noteID ) ) {
			$resp->message = __( 'Note not found.', 'wp-simple-firewall' );
		}
		else {
			try {
				$resp->success = $this->getCon()
									  ->getModule_Plugin()
									  ->getDbHandler_Notes()
									  ->getQueryDeleter()
									  ->deleteById( $noteID );
				$resp->message = $resp->success ?
					__( 'Note deleted', 'wp-simple-firewall' )
					: __( "Note couldn't be deleted", 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$resp->message = $e->getMessage();
			}
		}
	}
}