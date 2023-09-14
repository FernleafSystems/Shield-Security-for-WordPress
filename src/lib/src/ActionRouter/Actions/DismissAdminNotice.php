<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class DismissAdminNotice extends BaseAction {

	public const SLUG = 'dismiss_admin_notice';

	protected function exec() {
		$noticeID = sanitize_key( Services::Request()->query( 'notice_id', '' ) );
		// TODO: put all the notices into a single (plugin) module. This means transferring the dismissed_notices option
		foreach ( self::con()->modules as $module ) {
			$notices = $module->getAdminNotices();
			foreach ( $notices->getAdminNotices() as $notice ) {
				if ( $noticeID == $notice->id ) {
					$notices->setNoticeDismissed( $notice );
					$this->response()->action_response_data = [
						'success'   => true,
						'message'   => 'Admin notice dismissed', //not currently rendered
						'notice_id' => $notice->id,
					];
					break;
				}
			}
		}
	}
}