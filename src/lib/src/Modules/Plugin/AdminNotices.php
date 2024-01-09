<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

/**
 * @deprecated 18.6
 */
class AdminNotices extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\AdminNotices {

	private function buildNotice_OverrideForceoff( NoticeVO $notice ) {
	}

	private function buildNotice_SiteLockdownActive( NoticeVO $notice ) {
	}

	private function buildNotice_AllowTracking( NoticeVO $notice ) {
	}

	private function buildNotice_RatePlugin( NoticeVO $notice ) {
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return false;
	}
}