<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes\Insert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class AdminNoteInsert extends PluginBase {

	public const SLUG = 'admin_note_insert';

	public function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$resp = $this->response();

		$note = trim( FormParams::Retrieve()[ 'admin_note' ] ?? '' );
		if ( !$mod->getCanAdminNotes() ) {
			$resp->message = __( "Sorry, the Admin Notes feature isn't available.", 'wp-simple-firewall' );
		}
		elseif ( empty( $note ) ) {
			$resp->message = __( 'Sorry, but it appears your note was empty.', 'wp-simple-firewall' );
		}
		else {
			/** @var Insert $inserter */
			$inserter = $mod->getDbHandler_Notes()->getQueryInserter();
			$resp->success = $inserter->create( $note );
			$resp->message = $resp->success ? __( 'Note created successfully.', 'wp-simple-firewall' )
				: __( 'Note could not be created.', 'wp-simple-firewall' );
		}
	}
}