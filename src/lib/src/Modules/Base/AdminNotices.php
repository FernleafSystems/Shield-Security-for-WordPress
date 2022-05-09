<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	protected static $nCount = 0;

	protected function canRun() :bool {
		return Services::WpUsers()->isUserLoggedIn();
	}

	public function getNotices() :array {
		return $this->buildNotices();
	}

	/**
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	protected function buildNotices() :array {
		$notices = [];

		foreach ( $this->getAdminNotices() as $notice ) {
			$this->preProcessNotice( $notice );
			if ( $notice->display ) {
				try {
					$this->processNotice( $notice );
					if ( $notice->display ) {
						$notices[] = $notice;
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}

		return $notices;
	}

	/**
	 * @return NoticeVO[]
	 */
	public function getAdminNotices() :array {
		return array_map(
			function ( $noticeDef ) {
				$noticeDef = Services::DataManipulation()
									 ->mergeArraysRecursive(
										 [
											 'schedule'         => 'conditions',
											 'type'             => 'promo',
											 'plugin_page_only' => true,
											 'valid_admin'      => true,
											 'plugin_admin'     => 'yes',
											 'can_dismiss'      => true,
											 'per_user'         => false,
											 'display'          => false,
											 'min_install_days' => 0,
											 'twig'             => true,
											 'mod'              => $this->getMod()->getSlug(),
										 ],
										 $noticeDef
									 );
				return ( new NoticeVO() )->applyFromArray( $noticeDef );
			},
			$this->getOptions()->getAdminNotices()
		);
	}

	protected function preProcessNotice( NoticeVO $notice ) {
		$con = $this->getCon();
		$opts = $this->getOptions();

		if ( $notice->plugin_page_only && !$con->isModulePage() ) {
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
		$dismissedUser = $this->isNoticeDismissedForCurrentUser( $notice );

		$allDisd = $this->getMod()->getDismissedNotices();
		$dismissedMod = isset( $allDisd[ $notice->id ] ) && $allDisd[ $notice->id ] > 0;

		if ( !$notice->per_user && $dismissedUser && !$dismissedMod ) {
			$this->setNoticeDismissed( $notice );
		}

		return $dismissedUser || $dismissedMod;
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return true;
	}

	protected function isNoticeDismissedForCurrentUser( NoticeVO $notice ) :bool {
		$dismissed = false;

		$meta = $this->getCon()->getCurrentUserMeta();
		if ( !empty( $meta ) ) {
			$noticeMetaKey = $this->getNoticeMetaKey( $notice );

			if ( isset( $meta->{$noticeMetaKey} ) ) {
				$dismissed = true;

				// migrate from old-style array storage to plain Timestamp
				if ( is_array( $meta->{$noticeMetaKey} ) ) {
					$meta->{$noticeMetaKey} = $meta->{$noticeMetaKey}[ 'time' ];
				}
			}
		}

		return $dismissed;
	}

	/**
	 * @throws \Exception
	 */
	protected function processNotice( NoticeVO $notice ) {
		throw new \Exception( 'Unsupported Notice ID: '.$notice->id );
	}

	public function setNoticeDismissed( NoticeVO $notice ) {
		$ts = Services::Request()->ts();

		$meta = $this->getCon()->getCurrentUserMeta();
		$noticeMetaKey = $this->getNoticeMetaKey( $notice );

		if ( $notice->per_user ) {
			if ( !empty( $meta ) ) {
				$meta->{$noticeMetaKey} = $ts;
			}
		}
		else {
			$mod = $this->getMod();
			$allDismissed = $mod->getDismissedNotices();
			$allDismissed[ $notice->id ] = $ts;
			$mod->setDismissedNotices( $allDismissed );

			// Clear out any old
			if ( !empty( $meta ) ) {
				unset( $meta->{$noticeMetaKey} );
			}
		}
	}

	private function getNoticeMetaKey( NoticeVO $notice ) :string {
		return 'notice_'.str_replace( [ '-', '_' ], '', $notice->id );
	}
}