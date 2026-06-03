<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\PhpFutureMinimum;

class DismissAdminNotice extends BaseAction {

	use Traits\SecurityAdminNotRequired;

	public const SLUG = 'dismiss_admin_notice';

	protected function exec() {
		$noticeID = sanitize_key( $this->action_data[ 'notice_id' ] ?? '' );
		if ( empty( $noticeID ) ) {
			return;
		}

		if ( !$this->dismissLegacyNotice( $noticeID ) ) {
			$this->dismissPluginNotice( $noticeID );
		}
	}

	private function dismissLegacyNotice( string $noticeID ) :bool {
		foreach ( self::con()->admin_notices->getAdminNotices() as $notice ) {
			if ( $noticeID === $notice->id ) {
				if ( $notice->can_dismiss ) {
					self::con()->admin_notices->setNoticeDismissed( $notice );
					$this->setDismissedResponse( $notice->id );
				}
				return true;
			}
		}

		return false;
	}

	private function dismissPluginNotice( string $noticeID ) :void {
		if ( $noticeID !== PhpFutureMinimum::ID ) {
			return;
		}

		$notice = ( new PhpFutureMinimum() )->check();
		if ( $notice !== null && (bool)$notice[ 'can_dismiss' ] && PhpFutureMinimum::snoozeCurrentUser() ) {
			$this->setDismissedResponse( PhpFutureMinimum::ID );
		}
	}

	private function setDismissedResponse( string $noticeID ) :void {
		$this->response()->setPayload( [
			'message'   => __( 'Admin notice dismissed', 'wp-simple-firewall' ),
			//not currently rendered
			'notice_id' => $noticeID,
		] )->setPayloadSuccess( true );
	}
}
