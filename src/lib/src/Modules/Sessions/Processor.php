<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * @var Session\EntryVO
	 */
	private $current;

	public function run() {
		if ( !Services::WpUsers()->isProfilePage() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				$mod->getSessionCon()->terminateCurrentSession();
			}, 0 );
		}

		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user ) {
		if ( !$user instanceof \WP_User ) {
			$user = Services::WpUsers()->getUserByUsername( $username );
		}
		$this->activateUserSession( $user );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->activateUserSession( Services::WpUsers()->getUserById( $nUserId ) );
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

	/**
	 * @param \WP_User $user
	 * @return bool
	 */
	private function activateUserSession( $user ) {
		if ( !$this->isLoginCaptured() && $user instanceof \WP_User ) {
			$this->setLoginCaptured();
			/** @var ModCon $mod */
			$mod = $this->getMod();
			// If they have a currently active session, terminate it (i.e. we replace it)
			$mod->getSessionCon()->terminateCurrentSession();
			$mod->getSessionCon()->queryCreateSession( $this->getCon()->getSessionId( true ), $user );
		}
		return true;
	}

	/**
	 * @return bool
	 * @deprecated 10.1
	 */
	public function terminateCurrentSession() {
		$success = false;

		$oSes = $this->getCurrentSession();
		if ( $oSes instanceof Session\EntryVO ) {
			$success = ( new Lib\Ops\Terminate() )
				->setMod( $this->getMod() )
				->byRecordId( $oSes->id );
		}

		$this->current = null;
		$this->getCon()->clearSession();

		return $success;
	}

	/**
	 * @return Session\EntryVO|null
	 * @deprecated 10.1
	 */
	public function getCurrentSession() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return empty( $this->current ) ? $mod->getSessionCon()->getCurrent() : $this->current;
	}

	/**
	 * @return Session\EntryVO|null
	 * @deprecated 10.1
	 */
	public function loadCurrentSession() {
		$sess = null;
		$con = $this->getCon();
		if ( did_action( 'init' ) && $con->hasSessionId() ) {
			$sess = $this->queryGetSession( $con->getSessionId() );
		}
		return $sess;
	}

	/**
	 * @param string $sessionID
	 * @param string $username
	 * @return bool
	 * @deprecated 10.1
	 */
	protected function queryCreateSession( $sessionID, $username ) {
		return false;
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $username
	 * @param string $sessionID
	 * @return Session\EntryVO|null
	 * @deprecated 10.1
	 */
	private function queryGetSession( $sessionID, $username = '' ) {
		return null;
	}
}