<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta;

class AdminNotices {

	use Shield\Modules\ModConsumer;

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

		foreach ( $this->getAdminNotices() as $oNotice ) {
			$this->preProcessNotice( $oNotice );
			if ( $oNotice->display ) {
				try {
					$this->processNotice( $oNotice );
					if ( $oNotice->display ) {
						$aNotices[] = $oNotice;
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
										   'per_user'         => false,
										   'display'          => true,
										   'min_install_days' => 0,
										   'twig'             => true,
									   ],
									   $aNotDef
								   );
				return ( new Shield\Utilities\AdminNotices\NoticeVO() )->applyFromArray( $aNotDef );
			},
			$this->getMod()->getOptions()->getAdminNotices()
		);
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	protected function preProcessNotice( $oNotice ) {
		$oCon = $this->getCon();
		$oMod = $this->getMod();
		$oOpts = $oMod->getOptions();
		if ( $this->isNoticeDismissed( $oNotice ) ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->plugin_page_only && !$oCon->isModulePage() ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->type == 'promo' && !$this->getMod()->getOptions()->isShowPromoAdminNotices() ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->valid_admin && !$oCon->isValidAdminArea() ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->plugin_admin == 'yes' && !$oCon->isPluginAdmin() ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->plugin_admin == 'no' && $oCon->isPluginAdmin() ) {
			$oNotice->display = false;
		}
		else if ( $oNotice->min_install_days > 0 && $oNotice->min_install_days < $oOpts->getInstallationDays() ) {
			$oNotice->display = false;
		}
		$oNotice->template = '/notices/'.$oNotice->id;
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
	protected function isNoticeDismissedForCurrentUser( $oNotice ) {
		$bDismissed = false;

		$oMeta = $this->getCon()->getCurrentUserMeta();
		if ( $oMeta instanceof PluginUserMeta ) {
			$sCleanNotice = 'notice_'.str_replace( [ '-', '_' ], '', $oNotice->id );

			if ( isset( $oMeta->{$sCleanNotice} ) ) {
				$bDismissed = true;

				// migrate from old-style array storage to plain Timestamp
				if ( is_array( $oMeta->{$sCleanNotice} ) ) {
					$oMeta->{$sCleanNotice} = $oMeta->{$sCleanNotice}[ 'time' ];
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

		if ( $oNotice->per_user ) {
			$oMeta = $this->getCon()->getCurrentUserMeta();
			if ( $oMeta instanceof PluginUserMeta ) {
				$sCleanNotice = 'notice_'.str_replace( [ '-', '_' ], '', $oNotice->id );
				$oMeta->{$sCleanNotice} = $nTs;
			}
		}
		else {
			$oMod = $this->getMod();
			$aDismissed = $oMod->getDismissedNotices();
			$aDismissed[ $oNotice->id ] = $nTs;
			$oMod->setDismissedNotices( $aDismissed );
		}
		return $this;
	}
}