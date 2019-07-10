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

	/**
	 * @param string $sMessage
	 * @param bool   $bIsError
	 * @return $this
	 */
	public function addFlash( $sMessage, $bIsError = false ) {
		Services::Response()->cookieSet(
			$this->getCon()->prefix( 'flash' ),
			base64_encode( json_encode( [
				'message' => sanitize_text_field( $sMessage ),
				'error'   => $bIsError
			] ) ),
			8
		);
		return $this;
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
		$aNotices[] = $this->getFlashNotice();
		return array_filter(
			$aNotices,
			function ( $oNotice ) {
				return ( $oNotice instanceof NoticeVO );
			}
		);
	}

	/**
	 * @return NoticeVO|null
	 */
	private function getFlashNotice() {
		$oNotice = null;
		$sCookieName = $this->getCon()->prefix( 'flash' );
		$sMessage = Services::Request()->cookie( $sCookieName, '' );
		if ( !empty( $sMessage ) ) {
			$aMess = json_decode( base64_decode( $sMessage ), true );
			if ( !empty( $aMess[ 'message' ] ) ) {
				$oNotice = new NoticeVO();
				$oNotice->type = $aMess[ 'error' ] ? 'error' : 'updated';
				$oNotice->display = true;
				$oNotice->render_data = [
					'notice_classes' => [
						'flash',
						$oNotice->type
					],
					'message' => sanitize_text_field( $aMess[ 'message' ] ),
				];
				$oNotice->template = '/notices/flash-message.twig';
			}
			Services::Response()->cookieDelete( $sCookieName );
		}
		return $oNotice;
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