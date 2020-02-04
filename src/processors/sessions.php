<?php

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Sessions extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var Session\EntryVO
	 */
	private $oCurrent;

	public function run() {
		if ( !Services::WpUsers()->isProfilePage() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				$this->terminateCurrentSession();
			}, 0 );
		}
		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		if ( !$oUser instanceof \WP_User ) {
			$oUser = Services::WpUsers()->getUserByUsername( $sUsername );
		}
		$this->activateUserSession( $oUser );
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

	/**
	 */
	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->autoAddSession();
		}
	}

	public function onModuleShutdown() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();

		if ( !Services::Rest()->isRest() ) {
			$oSession = $this->getCurrentSession();
			if ( $oSession instanceof Session\EntryVO ) {
				/** @var Session\Update $oUpd */
				$oUpd = $oMod->getDbHandler_Sessions()->getQueryUpdater();
				$oUpd->updateLastActivity( $this->getCurrentSession() );
			}
		}

		parent::onModuleShutdown();
	}

	private function autoAddSession() {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		if ( !$oMod->getSession() && $oMod->isAutoAddSessions() ) {
			$this->queryCreateSession(
				$this->getCon()->getSessionId( true ),
				Services::WpUsers()->getCurrentWpUsername()
			);
		}
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $sMessage
	 * @return string
	 * @throws \Exception
	 */
	public function printLinkToAdmin( $sMessage = '' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		$oU = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && ( $oU instanceof \WP_User ) && $oMod->hasSession() ) {
			$sMessage .= sprintf( '<p class="message">%s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' )
				.sprintf( ' <span style="white-space: nowrap">(%s)</span>', $oU->user_login ),
				( $oU->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}
		return $sMessage;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function activateUserSession( $oUser ) {
		if ( !$this->isLoginCaptured() && $oUser instanceof \WP_User ) {
			$this->setLoginCaptured();
			// If they have a currently active session, terminate it (i.e. we replace it)
			$this->terminateCurrentSession();
			$this->queryCreateSession( $this->getCon()->getSessionId( true ), $oUser->user_login );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function terminateCurrentSession() {
		$bSuccess = false;

		$oSes = $this->getCurrentSession();
		if ( $oSes instanceof Session\EntryVO ) {
			$bSuccess = ( new Sessions\Lib\Ops\Terminate() )
				->setMod( $this->getMod() )
				->byRecordId( $oSes->id );
		}

		$this->oCurrent = null;
		$this->getCon()->clearSession();

		return $bSuccess;
	}

	/**
	 * @return Session\EntryVO|null
	 */
	public function getCurrentSession() {
		if ( empty( $this->oCurrent ) ) {
			$this->oCurrent = $this->loadCurrentSession();
		}
		return $this->oCurrent;
	}

	/**
	 * @return Session\EntryVO|null
	 */
	public function loadCurrentSession() {
		$oSession = null;
		$oCon = $this->getCon();
		if ( did_action( 'init' ) && $oCon->hasSessionId() ) {
			$oSession = $this->queryGetSession( $oCon->getSessionId() );
		}
		return $oSession;
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	protected function queryCreateSession( $sSessionId, $sUsername ) {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		if ( empty( $sSessionId ) || empty( $sUsername ) ) {
			return null;
		}

		$this->getCon()->fireEvent( 'session_start' );

		/** @var Session\Insert $oInsert */
		$oInsert = $oMod->getDbHandler_Sessions()->getQueryInserter();
		return $oInsert->create( $sSessionId, $sUsername );
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return Session\EntryVO|null
	 */
	private function queryGetSession( $sSessionId, $sUsername = '' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Sessions $oMod */
		$oMod = $this->getMod();
		/** @var Session\Select $oSel */
		$oSel = $oMod->getDbHandler_Sessions()->getQuerySelector();
		return $oSel->retrieveUserSession( $sSessionId, $sUsername );
	}
}