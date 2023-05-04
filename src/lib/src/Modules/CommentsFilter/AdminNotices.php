<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

/**
 * @deprecated 18.1
 */
class AdminNotices extends Shield\Modules\Base\AdminNotices {

	protected function processNotice( NoticeVO $notice ) {
	}

	private function buildNotice_AkismetRunning( NoticeVO $notice ) {
	}

	protected function isDisplayNeeded( Shield\Utilities\AdminNotices\NoticeVO $notice ) :bool {
		return false;
	}
}