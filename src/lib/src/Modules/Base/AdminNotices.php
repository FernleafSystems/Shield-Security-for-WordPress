<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta;

class AdminNotices {

	use Shield\Modules\ModConsumer;

	/**
	 * @var
	 */
	static protected $nCount = 0;

	public function run() {
		$oMod = $this->getMod();
		add_filter( $oMod->prefix( 'collectNotices' ), [ $this, 'addNotices' ] );
		add_filter( $oMod->prefix( 'ajaxAuthAction' ), [ $this, 'handleAuthAjax' ] );
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {
		if ( empty( $aAjaxResponse ) && Services::Request()->request( 'exec' ) === 'dismiss_admin_notice' ) {
			$aAjaxResponse = $this->ajaxExec_DismissAdminNotice();
		}
		return $aAjaxResponse;
	}

	/**
	 * @return array
	 */
	protected function ajaxExec_DismissAdminNotice() {
		$aAjaxResponse = [];

		$sNoticeId = sanitize_key( Services::Request()->query( 'notice_id', '' ) );

		foreach ( $this->getAdminNotices() as $oNotice ) {
			if ( $sNoticeId == $oNotice->id ) {
				$this->setNoticeDismissed( $oNotice );
				$aAjaxResponse = [
					'success'   => true,
					'message'   => 'Admin notice dismissed', //not currently seen
					'notice_id' => $oNotice->id,
				];
				break;
			}
		}

		// leave response empty if it doesn't apply here, so other modules can process it.
		return $aAjaxResponse;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO[] $aNotices
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	public function addNotices( $aNotices ) {
		return array_merge( $aNotices, $this->buildNotices() );
	}

	/**
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	protected function buildNotices() {
		$aNotices = [];

		foreach ( $this->getAdminNotices() as $oNtc ) {
			$this->preProcessNotice( $oNtc );
			if ( $oNtc->display ) {
				try {
					$this->processNotice( $oNtc );
					if ( $oNtc->display ) {
						$aNotices[] = $oNtc;
					}
				}
				catch ( \Exception $oE ) {
				}
			}
		}

		return $aNotices;
	}

	/**
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	protected function getAdminNotices() {
		return array_map(
			function ( $aNotDef ) {
				$aNotDef = Services::DataManipulation()
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
									   $aNotDef
								   );
				return ( new Shield\Utilities\AdminNotices\NoticeVO() )->applyFromArray( $aNotDef );
			},
			$this->getOptions()->getAdminNotices()
		);
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNtc
	 */
	protected function preProcessNotice( $oNtc ) {
		$oCon = $this->getCon();
		$oMod = $this->getMod();
		$oOpts = $oMod->getOptions();

		if ( $oNtc->plugin_page_only && !$oCon->isModulePage() ) {
			$oNtc->non_display_reason = 'plugin_page_only';
		}
		else if ( $oNtc->type == 'promo' && !$this->getOptions()->isShowPromoAdminNotices() ) {
			$oNtc->non_display_reason = 'promo_hidden';
		}
		else if ( $oNtc->valid_admin && !$oCon->isValidAdminArea() ) {
			$oNtc->non_display_reason = 'not_admin_area';
		}
		else if ( $oNtc->plugin_admin == 'yes' && !$oCon->isPluginAdmin() ) {
			$oNtc->non_display_reason = 'not_plugin_admin';
		}
		else if ( $oNtc->plugin_admin == 'no' && $oCon->isPluginAdmin() ) {
			$oNtc->non_display_reason = 'is_plugin_admin';
		}
		else if ( $oNtc->min_install_days > 0 && $oNtc->min_install_days > $oOpts->getInstallationDays() ) {
			$oNtc->non_display_reason = 'min_install_days';
		}
		else if ( static::$nCount > 0 && $oNtc->type !== 'error' ) {
			$oNtc->non_display_reason = 'max_nonerror_count';
		}
		else if ( $oNtc->can_dismiss && $this->isNoticeDismissed( $oNtc ) ) {
			$oNtc->non_display_reason = 'dismissed';
		}
		else if ( !$this->isDisplayNeeded( $oNtc ) ) {
			$oNtc->non_display_reason = 'not_needed';
		}
		else {
			static::$nCount++;
			$oNtc->display = true;
			$oNtc->non_display_reason = 'n/a';
		}

		$oNtc->template = '/notices/'.$oNtc->id;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isNoticeDismissed( $oNotice ) {
		$bDismissedUser = $this->isNoticeDismissedForCurrentUser( $oNotice );

		$aDisd = $this->getMod()->getDismissedNotices();
		$bDismissedMod = isset( $aDisd[ $oNotice->id ] ) && $aDisd[ $oNotice->id ] > 0;

		if ( !$oNotice->per_user && $bDismissedUser && !$bDismissedMod ) {
			$this->setNoticeDismissed( $oNotice );
		}

		return $bDismissedUser || $bDismissedMod;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isDisplayNeeded( $oNotice ) {
		return true;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return bool
	 */
	protected function isNoticeDismissedForCurrentUser( $oNotice ) {
		$bDismissed = false;

		$oMeta = $this->getCon()->getCurrentUserMeta();
		if ( $oMeta instanceof PluginUserMeta ) {
			$sNoticeMetaKey = $this->getNoticeMetaKey( $oNotice );

			if ( isset( $oMeta->{$sNoticeMetaKey} ) ) {
				$bDismissed = true;

				// migrate from old-style array storage to plain Timestamp
				if ( is_array( $oMeta->{$sNoticeMetaKey} ) ) {
					$oMeta->{$sNoticeMetaKey} = $oMeta->{$sNoticeMetaKey}[ 'time' ];
				}
			}
		}

		return $bDismissed;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {
		throw new \Exception( 'Unsupported Notice ID: '.$oNotice->id );
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return $this
	 */
	protected function setNoticeDismissed( $oNotice ) {
		$nTs = Services::Request()->ts();

		$oMeta = $this->getCon()->getCurrentUserMeta();
		$sNoticeMetaKey = $this->getNoticeMetaKey( $oNotice );

		if ( $oNotice->per_user ) {
			if ( $oMeta instanceof PluginUserMeta ) {
				$oMeta->{$sNoticeMetaKey} = $nTs;
			}
		}
		else {
			$oMod = $this->getMod();
			$aDismissed = $oMod->getDismissedNotices();
			$aDismissed[ $oNotice->id ] = $nTs;
			$oMod->setDismissedNotices( $aDismissed );

			// Clear out any old
			if ( $oMeta instanceof PluginUserMeta ) {
				unset( $oMeta->{$sNoticeMetaKey} );
			}
		}
		return $this;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @return string
	 */
	private function getNoticeMetaKey( $oNotice ) {
		return 'notice_'.str_replace( [ '-', '_' ], '', $oNotice->id );
	}
}