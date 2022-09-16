<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class DismissAdminNotice extends PluginBase {

	const SLUG = 'dismiss_admin_notice';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$noticeID = sanitize_key( Services::Request()->query( 'notice_id', '' ) );

		$notices = $this->getMod()->getAdminNotices();
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