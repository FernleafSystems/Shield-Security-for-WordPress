<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class DismissAdminNotice extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'dismiss_admin_notice';

	protected function exec() {
		$noticeID = sanitize_key( $this->action_data[ 'notice_id' ] );
		if ( !empty( $noticeID ) ) {
			foreach ( self::con()->admin_notices->getAdminNotices() as $notice ) {
				if ( $noticeID == $notice->id ) {
					self::con()->admin_notices->setNoticeDismissed( $notice );
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