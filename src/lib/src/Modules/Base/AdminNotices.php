<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

/**
 * @deprecated 18.6
 */
class AdminNotices extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	protected static $nCount = 0;

	protected function canRun() :bool {
		return false;
	}

	public function getNotices() :array {
		return [];
	}

	/**
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	protected function buildNotices() :array {
		return [];
	}

	/**
	 * @return NoticeVO[]
	 */
	public function getAdminNotices() :array {
		return [];
	}

	protected function preProcessNotice( NoticeVO $notice ) {
		$con = self::con();
		$opts = $this->opts();

		if ( $notice->plugin_page_only && !$con->isPluginAdminPageRequest() ) {
			$notice->non_display_reason = 'plugin_page_only';
		}
		elseif ( $notice->type == 'promo' && !$opts->isShowPromoAdminNotices() ) {
			$notice->non_display_reason = 'promo_hidden';
		}
		elseif ( $notice->valid_admin && !$con->isValidAdminArea() ) {
			$notice->non_display_reason = 'not_admin_area';
		}
		elseif ( $notice->plugin_admin == 'yes' && !$con->isPluginAdmin() ) {
			$notice->non_display_reason = 'not_plugin_admin';
		}
		elseif ( $notice->plugin_admin == 'no' && $con->isPluginAdmin() ) {
			$notice->non_display_reason = 'is_plugin_admin';
		}
		elseif ( $notice->min_install_days > 0 && $notice->min_install_days > $opts->getInstallationDays() ) {
			$notice->non_display_reason = 'min_install_days';
		}
		elseif ( static::$nCount > 0 && $notice->type !== 'error' ) {
			$notice->non_display_reason = 'max_nonerror_count';
		}
		elseif ( $notice->can_dismiss && $this->isNoticeDismissed( $notice ) ) {
			$notice->non_display_reason = 'dismissed';
		}
		elseif ( !$this->isDisplayNeeded( $notice ) ) {
			$notice->non_display_reason = 'not_needed';
		}
		else {
			static::$nCount++;
			$notice->display = true;
			$notice->non_display_reason = 'n/a';
		}

		$notice->template = '/notices/'.$notice->id;
	}

	protected function isNoticeDismissed( NoticeVO $notice ) :bool {
		return true;
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return true;
	}

	protected function isNoticeDismissedForCurrentUser( NoticeVO $notice ) :bool {
		return true;
	}

	/**
	 * @throws \Exception
	 */
	protected function processNotice( NoticeVO $notice ) {
		throw new \Exception( 'Unsupported Notice ID: '.$notice->id );
	}

	public function setNoticeDismissed( NoticeVO $notice ) {
	}

	private function getNoticeMetaKey( NoticeVO $notice ) :string {
		return 'notice_'.\str_replace( [ '-', '_' ], '', $notice->id );
	}
}