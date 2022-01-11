<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta;

class AdminNotices {

	use Shield\Modules\ModConsumer;

	protected static $nCount = 0;

	public function run() {
		add_filter( $this->getCon()->prefix( 'collectNotices' ), [ $this, 'addNotices' ] );
		add_filter( $this->getCon()->prefix( 'ajaxAuthAction' ), [ $this, 'handleAuthAjax' ] );
	}

	public function handleAuthAjax( array $ajaxResponse ) :array {
		if ( empty( $ajaxResponse ) && Services::Request()->request( 'exec' ) === 'dismiss_admin_notice' ) {
			$ajaxResponse = $this->ajaxExec_DismissAdminNotice();
		}
		return $ajaxResponse;
	}

	protected function ajaxExec_DismissAdminNotice() :array {
		$ajaxResponse = [];

		$noticeID = sanitize_key( Services::Request()->query( 'notice_id', '' ) );

		foreach ( $this->getAdminNotices() as $notice ) {
			if ( $noticeID == $notice->id ) {
				$this->setNoticeDismissed( $notice );
				$ajaxResponse = [
					'success'   => true,
					'message'   => 'Admin notice dismissed', //not currently seen
					'notice_id' => $notice->id,
				];
				break;
			}
		}

		// leave response empty if it doesn't apply here, so other modules can process it.
		return $ajaxResponse;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO[] $notices
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	public function addNotices( $notices ) {
		return array_merge( $notices, $this->buildNotices() );
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
	protected function getAdminNotices() :array {
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

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return true;
	}

	protected function isNoticeDismissedForCurrentUser( NoticeVO $notice ) :bool {
		$dismissed = false;

		$meta = $this->getCon()->getCurrentUserMeta();
		if ( $meta instanceof PluginUserMeta ) {
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

	protected function setNoticeDismissed( NoticeVO $notice ) {
		$ts = Services::Request()->ts();

		$meta = $this->getCon()->getCurrentUserMeta();
		$noticeMetaKey = $this->getNoticeMetaKey( $notice );

		if ( $notice->per_user ) {
			if ( $meta instanceof PluginUserMeta ) {
				$meta->{$noticeMetaKey} = $ts;
			}
		}
		else {
			$mod = $this->getMod();
			$allDismissed = $mod->getDismissedNotices();
			$allDismissed[ $notice->id ] = $ts;
			$mod->setDismissedNotices( $allDismissed );

			// Clear out any old
			if ( $meta instanceof PluginUserMeta ) {
				unset( $meta->{$noticeMetaKey} );
			}
		}
	}

	private function getNoticeMetaKey( NoticeVO $notice ) :string {
		return 'notice_'.str_replace( [ '-', '_' ], '', $notice->id );
	}
}