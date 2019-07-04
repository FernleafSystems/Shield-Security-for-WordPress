<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Controller {

	use PluginControllerConsumer;

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'onWpAdminNotices' ] );
		add_action( 'network_admin_notices', [ $this, 'onWpNetworkAdminNotices' ] );
		add_filter( $this->getCon()->prefix( 'ajaxAuthAction' ), [ $this, 'handleAuthAjax' ] );
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
		// Get all notices and if this notice exists, we set it to "hidden"
		$sNoticeId = sanitize_key( Services::Request()->query( 'notice_id', '' ) );
		$aNotices = apply_filters( $this->getPrefix().'register_admin_notices', [] );
		if ( !empty( $sNoticeId ) && array_key_exists( $sNoticeId, $aNotices ) ) {
			$this->setMeta( $aNotices[ $sNoticeId ][ 'id' ] );
		}
		return [ 'success' => true ];
	}

	public function onWpAdminNotices() {
		$this->displayNotices();
	}

	public function onWpNetworkAdminNotices() {
		$this->displayNotices();
	}

	protected function displayNotices() {
		/** @var NoticeVO[] $aNotices */
		$aNotices = apply_filters( $this->getCon()->prefix( 'collectNotices' ), [] );
		foreach ( $aNotices as $sKey => $oNotice ) {
			if ( $oNotice instanceof NoticeVO ) {
				echo $this->renderNotice( $oNotice );
			}
		}
	}

	/**
	 * @param NoticeVO $oNotice
	 * @return string
	 */
	protected function renderNotice( $oNotice ) {
		$aRenderVars = $oNotice->render_data;

		if ( empty( $aRenderVars[ 'notice_classes' ] ) || !is_array( $aRenderVars[ 'notice_classes' ] ) ) {
			$aRenderVars[ 'notice_classes' ] = [];
		}
		$aRenderVars[ 'notice_classes' ][] = $oNotice->type;
		if ( !in_array( 'error', $aRenderVars[ 'notice_classes' ] ) ) {
			$aRenderVars[ 'notice_classes' ][] = 'updated';
		}
		$aRenderVars[ 'notice_classes' ][] = 'notice-'.$oNotice->id;
		$aRenderVars[ 'notice_classes' ] = implode( ' ', array_unique( $aRenderVars[ 'notice_classes' ] ) );

		$aRenderVars[ 'unique_render_id' ] = uniqid( $oNotice->id );

		$aAjaxData = $this->getCon()->getNonceActionData( 'dismiss_admin_notice' );
		$aAjaxData[ 'hide' ] = 1;
		$aAjaxData[ 'notice_id' ] = $oNotice->id;
		$aRenderVars[ 'ajax' ][ 'dismiss_admin_notice' ] = json_encode( $aAjaxData );

		return $this->getCon()
					->getRenderer()
					->setTemplate( $oNotice->template )
					->setRenderVars( $aRenderVars )
					->setTemplateEngineTwig()
					->render();
	}
}