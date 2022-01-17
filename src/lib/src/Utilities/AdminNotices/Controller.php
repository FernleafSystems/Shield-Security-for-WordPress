<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta;

class Controller {

	use PluginControllerConsumer;

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'onWpAdminNotices' ] );
		add_action( 'network_admin_notices', [ $this, 'onWpNetworkAdminNotices' ] );
		add_filter( 'login_message', [ $this, 'onLoginMessage' ] );
	}

	/**
	 * TODO doesn't handle error message highlighting
	 * @param string $msg
	 * @return string
	 */
	public function onLoginMessage( $msg ) {
		$msg = $this->retrieveFlashMessage();
		if ( is_array( $msg ) && isset( $msg[ 'show_login' ] ) && $msg[ 'show_login' ] ) {
			$msg .= sprintf( '<p class="message">%s</p>', sanitize_text_field( $msg[ 'message' ] ) );
			error_log( $msg );
			$this->clearFlashMessage();
		}
		return $msg;
	}

	/**
	 * @param string $msg
	 * @param bool   $isError
	 * @param bool   $bShowOnLoginPage
	 * @return $this
	 */
	public function addFlash( $msg, $isError = false, $bShowOnLoginPage = false ) {
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( $meta instanceof PluginUserMeta ) {
			$meta->flash_msg = [
				'message'    => sanitize_text_field( $msg ),
				'expires_at' => Services::Request()->ts() + 20,
				'error'      => $isError,
				'show_login' => $bShowOnLoginPage,
			];
		}
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
	public function getFlashNotice() {
		$oNotice = null;
		$aM = $this->retrieveFlashMessage();
		if ( is_array( $aM ) ) {
			$oNotice = new NoticeVO();
			$oNotice->type = $aM[ 'error' ] ? 'error' : 'updated';
			$oNotice->render_data = [
				'notice_classes' => [
					'flash',
					$oNotice->type
				],
				'message'        => sanitize_text_field( $aM[ 'message' ] ),
			];
			$oNotice->template = '/notices/flash-message.twig';
			$oNotice->display = true;
			$this->clearFlashMessage();
		}
		return $oNotice;
	}

	/**
	 * @return array|null
	 */
	private function retrieveFlashMessage() {
		$aMessage = null;
		$oMeta = $this->getCon()->getCurrentUserMeta();
		if ( $oMeta instanceof PluginUserMeta && is_array( $oMeta->flash_msg ) ) {
			if ( empty( $aM[ 'expires_at' ] ) || Services::Request()->ts() < $aM[ 'expires_at' ] ) {
				$aMessage = $oMeta->flash_msg;
			}
			else {
				$this->clearFlashMessage();
			}
		}
		return $aMessage;
	}

	/**
	 * @return $this
	 */
	private function clearFlashMessage() {
		$oMeta = $this->getCon()->getCurrentUserMeta();
		if ( $oMeta instanceof PluginUserMeta && !empty( $oMeta->flash_msg ) ) {
			$oMeta->flash_msg = null;
		}
		return $this;
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