<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Controller {

	use PluginControllerConsumer;

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'onWpAdminNotices' ] );
		add_action( 'network_admin_notices', [ $this, 'onWpNetworkAdminNotices' ] );
	}

	public function onWpAdminNotices() {
		$this->displayNotices();
	}

	public function onWpNetworkAdminNotices() {
		$this->displayNotices();
	}

	protected function displayNotices() {
		foreach ( $this->collectAllPluginNotices() as $sKey => $oNotice ) {
			echo $this->renderNotice( $oNotice );
		}
	}

	/**
	 * @return NoticeVO[]
	 */
	protected function collectAllPluginNotices() {
		/** @var NoticeVO[] $aNotices */
		$aNotices = apply_filters( $this->getCon()->prefix( 'collectNotices' ), [] );
		if ( !is_array( $aNotices ) ) {
			$aNotices = [];
		}
		return array_filter(
			$aNotices,
			function ( $oNotice ) {
				return ( $oNotice instanceof NoticeVO );
			}
		);
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
		$aRenderVars[ 'notice_id' ] = $oNotice->id;

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