<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

/**
 * @deprecated 18.6
 */
class AdminNotices extends Shield\Modules\Base\AdminNotices {

	private function buildNotice_CertainOptionsRestricted( NoticeVO $notice ) {
	}

	private function buildNotice_AdminUsersRestricted( NoticeVO $notice ) {
	}
}