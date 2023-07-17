<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\Insert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class AdminNoteInsert extends BaseAction {

	public const SLUG = 'admin_note_insert';

	public function exec() {
		$resp = $this->response();

		$note = \trim( FormParams::Retrieve()[ 'admin_note' ] ?? '' );
		if ( !$this->con()->isPluginAdmin() ) {
			$resp->message = __( "Sorry, the Admin Notes feature isn't available.", 'wp-simple-firewall' );
		}
		elseif ( empty( $note ) ) {
			$resp->message = __( 'Sorry, but it appears your note was empty.', 'wp-simple-firewall' );
		}
		else {
			/** @var Insert $inserter */
			$inserter = $this->con()->getModule_Plugin()->getDbHandler_Notes()->getQueryInserter();
			$resp->success = $inserter->create( $note );
			$resp->message = $resp->success ?
				__( 'Note created successfully.', 'wp-simple-firewall' )
				: __( 'Note could not be created.', 'wp-simple-firewall' );
		}
	}
}