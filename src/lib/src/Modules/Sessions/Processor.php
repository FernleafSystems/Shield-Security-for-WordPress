<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	use WpLoginCapture;

	/**
	 * @var Session\EntryVO
	 */
	private $current;

	protected function run() {
		if ( !Services::WpUsers()->isProfilePage() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getSessionCon()->terminateCurrentSession();
			}, 0 );
		}

		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
		$this->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		$this->activateUserSession( $user );
	}

	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->autoAddSession();
		}
	}

	public function onModuleShutdown() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		if ( !Services::Rest()->isRest() && !$this->getCon()->plugin_deleting ) {
			$session = $mod->getSessionCon()->getCurrent();
			if ( $session instanceof Session\EntryVO ) {
				/** @var Session\Update $oUpd */
				$oUpd = $mod->getDbHandler_Sessions()->getQueryUpdater();
				$oUpd->updateLastActivity( $session );
			}
		}

		parent::onModuleShutdown();
	}

	private function autoAddSession() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$sessCon = $mod->getSessionCon();
		if ( !$sessCon->hasSession() && $mod->isAutoAddSessions() ) {
			$user = Services::WpUsers()->getCurrentWpUser();
			if ( $user instanceof \WP_User ) {
				$sessCon->queryCreateSession( $this->getCon()->getSessionId( true ), $user );
			}
		}
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $msg
	 * @return string
	 * @throws \Exception
	 */
	public function printLinkToAdmin( $msg = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && ( $user instanceof \WP_User ) && $mod->getSessionCon()->hasSession() ) {
			$msg .= sprintf( '<p class="message">%s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' )
				.sprintf( ' <span style="white-space: nowrap">(%s)</span>', $user->user_login ),
				( $user->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}
		return $msg;
	}

	private function activateUserSession( \WP_User $user ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		// If they have a currently active session, terminate it (i.e. we replace it)
		$mod->getSessionCon()->terminateCurrentSession();
		$mod->getSessionCon()->queryCreateSession( $this->getCon()->getSessionId( true ), $user );
	}
}