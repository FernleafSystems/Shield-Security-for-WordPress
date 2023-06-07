<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

/**
 * @deprecated 18.1
 */
class AdminNotices extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\AdminNotices {

	protected function processNotice( NoticeVO $notice ) {
	}

	private function buildNotice_WpHashesTokenFailure( NoticeVO $notice ) {
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return false;
	}
}