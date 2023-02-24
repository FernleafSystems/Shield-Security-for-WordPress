<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\AdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Users\UserMeta;

class Controller {

	use PluginControllerConsumer;

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'onWpAdminNotices' ] );
		add_action( 'network_admin_notices', [ $this, 'onWpNetworkAdminNotices' ] );
		add_filter( 'login_message', [ $this, 'onLoginMessage' ] );
	}

	/**
	 * TODO doesn't handle error message highlighting
	 * @param string $loginMsg
	 * @return string
	 */
	public function onLoginMessage( $loginMsg ) {
		$msg = $this->retrieveFlashMessage();
		if ( is_array( $msg ) && isset( $msg[ 'show_login' ] ) && $msg[ 'show_login' ] ) {
			$loginMsg .= sprintf( '<p class="message">%s</p>', sanitize_text_field( $msg[ 'message' ] ) );
			$this->clearFlashMessage();
		}
		return $loginMsg;
	}

	/**
	 * @param \WP_User|null $user
	 * @param bool          $isError
	 * @param bool          $bShowOnLoginPage
	 * @return $this
	 */
	public function addFlash( string $msg, $user = null, $isError = false, $bShowOnLoginPage = false ) {
		$con = $this->getCon();
		$meta = $user instanceof \WP_User ? $con->user_metas->for( $user ) : $con->getCurrentUserMeta();

		$msg = trim( sanitize_text_field( $msg ) );
		if ( !empty( $msg ) && $meta instanceof UserMeta ) {
			$meta->flash_msg = [
				'message'    => sprintf( '[%s] %s', $this->getCon()->getHumanName(), $msg ),
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
		foreach ( $this->collectAllPluginNotices() as $notice ) {
			echo $this->getCon()->action_router->render( AdminNotice::SLUG, [
				'raw_notice_data' => $notice->getRawData()
			] );
		}
	}

	/**
	 * @return NoticeVO[]
	 */
	protected function collectAllPluginNotices() :array {
		/** @var NoticeVO[] $notices */
		$notices = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$notices = array_merge( $notices, $mod->getAdminNotices()->getNotices() );
		}
		$notices[] = $this->getFlashNotice();
		return array_filter(
			$notices,
			function ( $notice ) {
				return $notice instanceof NoticeVO;
			}
		);
	}

	/**
	 * @return NoticeVO|null
	 */
	public function getFlashNotice() {
		$notice = null;
		$msg = $this->retrieveFlashMessage();
		if ( is_array( $msg ) ) {
			$notice = new NoticeVO();
			$notice->type = $msg[ 'error' ] ? 'error' : 'updated';
			$notice->render_data = [
				'notice_classes' => [
					'flash',
					$notice->type
				],
				'message'        => sanitize_text_field( $msg[ 'message' ] ),
			];
			$notice->template = '/notices/flash-message.twig';
			$notice->display = true;
			$this->clearFlashMessage();
		}
		return $notice;
	}

	/**
	 * @return array|null
	 */
	private function retrieveFlashMessage() {
		$msg = null;
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( $meta instanceof UserMeta && is_array( $meta->flash_msg ) ) {
			if ( empty( $meta->flash_msg[ 'expires_at' ] ) || Services::Request()
																	  ->ts() < $meta->flash_msg[ 'expires_at' ] ) {
				$msg = $meta->flash_msg;
			}
			else {
				$this->clearFlashMessage();
			}
		}
		return $msg;
	}

	private function clearFlashMessage() :self {
		$meta = $this->getCon()->getCurrentUserMeta();
		if ( $meta instanceof UserMeta && !empty( $meta->flash_msg ) ) {
			$meta->flash_msg = null;
		}
		return $this;
	}
}