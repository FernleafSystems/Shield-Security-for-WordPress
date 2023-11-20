<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class DismissAdminNotice extends BaseAction {

	use SecurityAdminNotRequired;

	public const SLUG = 'dismiss_admin_notice';

	protected function exec() {
		$noticeID = sanitize_key( $this->action_data[ 'notice_id' ] );
		if ( !empty( $noticeID ) ) {
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
}