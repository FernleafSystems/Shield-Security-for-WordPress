<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_UserManagement_Sessions extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
		add_filter( 'auth_cookie_expiration', array( $this, 'setTimeoutCookieExpiration_Filter' ), 100, 1 );
		add_action( 'wp_loaded', array( $this, 'onWpLoaded' ), 1 ); // Check the current every page load.
		add_action( 'wp_login', array( $this, 'onWpLogin' ), 10, 1 );
	}

	/**
	 * @param string $sUsername
	 */
	public function onWpLogin( $sUsername ) {
		$this->enforceSessionLimits( $sUsername );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->loadWpUsers()->isUserLoggedIn() && !$this->loadWp()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	/**
	 */
	private function checkCurrentSession() {
		$oWp = $this->loadWp();
		$oWpUsers = $this->loadWpUsers();

		$nCode = $this->assessCurrentSession();

		if ( $nCode > 0 ) { // it's not admin, but the user looks logged into WordPress and not to Shield

			if ( is_admin() ) { // prevent any admin access on invalid Shield sessions.

				switch ( $nCode ) {

					case 7:
						$oWpUsers->logoutUser( true );
						$this->addToAuditEntry(
							sprintf( 'Browser signature has changed for this user "%s" session. Redirecting request.', $oWpUsers->getCurrentWpUser()->user_login ),
							2,
							'um_session_browser_lock_redirect'
						);
						$oWp->redirectToLogin();
						break;

					case 3:
						// $this->loadWpUsers()->logoutUser( true ); // so as not to destroy the original, legitimate session
						$this->addToAuditEntry(
							sprintf( 'Access to an established user session from a new IP address "%s". Redirecting request.', $this->ip() ),
							2,
							'um_session_ip_lock_redirect'
						);
						$oWp->redirectToHome();
						break;

					default:
						$this->addToAuditEntry(
							'Unable to verify the current User Session. Forcefully logging out session and redirecting to login.',
							2,
							'um_session_not_found_redirect'
						);
						$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => $nCode ) );
						break;
				}
			}
			else {
				$this->addToAuditEntry(
					'Unable to verify the current User Session. Forcefully logging out session.',
					2,
					'um_session_not_found'
				);
				$oWpUsers->logoutUser();
			}
		}
	}

	/**
	 * @return int
	 */
	protected function assessCurrentSession() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();

		if ( !$oFO->hasSession() ) {
			$nForceLogOutCode = 4;
		}
		else {
			$oSess = $oFO->getSession();
			$nTime = $this->time();
			$nTimeout = $this->getSessionTimeoutInterval();
			$nIdleTimeout = $this->getSessionIdleTimeoutInterval();

			$nForceLogOutCode = 0; // when it's == 0 it's a valid session

			// timeout interval
			if ( $nTimeout > 0 && ( $nTime - $oSess->getLoggedInAt() > $nTimeout ) ) {
				$nForceLogOutCode = 1;
			} // idle timeout interval
			else if ( $nIdleTimeout > 0 && ( ( $nTime - $oSess->getLastActivityAt() ) > $nIdleTimeout ) ) {
				$oFO->setOptInsightsAt( 'last_idle_logout_at' );
				$nForceLogOutCode = 2;
			} // login ip address lock
			else if ( $this->isLockToIp() && ( $this->ip() != $oSess->getIp() ) ) { //TODO: sha1
				$nForceLogOutCode = 3;
			}
		}

		return $nForceLogOutCode;
	}

	/**
	 * @param integer $nTimeout
	 * @return integer
	 */
	public function setTimeoutCookieExpiration_Filter( $nTimeout ) {
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		return ( $nSessionTimeoutInterval > 0 ) ? $nSessionTimeoutInterval : $nTimeout;
	}

	/**
	 * @return integer
	 */
	protected function getSessionTimeoutInterval() {
		return $this->getOption( 'session_timeout_interval' )*DAY_IN_SECONDS;
	}

	/**
	 * @return integer
	 */
	protected function getSessionIdleTimeoutInterval() {
		return $this->getOption( 'session_idle_timeout_interval' )*HOUR_IN_SECONDS;
	}

	/**
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function getActiveSessionRecordsForUsername( $sWpUsername ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();
		return $oFO->getSessionsProcessor()
				   ->queryGetActiveSessionsForUsername( $sWpUsername );
	}

	/**
	 * @return bool
	 */
	protected function isLockToIp() {
		return $this->getFeature()->getOptIs( 'session_lock_location', 'Y' );
	}

	/**
	 * @param string $sUsername
	 */
	protected function enforceSessionLimits( $sUsername ) {

		$nSessionLimit = $this->getOption( 'session_username_concurrent_limit', 1 );
		if ( $nSessionLimit > 0 ) {

			$aSessions = $this->getActiveSessionRecordsForUsername( $sUsername );
			$nSessionsToKill = count( $aSessions ) - $nSessionLimit;
			if ( $nSessionsToKill > 0 ) {

				/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
				$oFO = $this->getFeature();
				$oSessProcessor = $oFO->getSessionsProcessor();

				for ( $nCount = 0 ; $nCount < $nSessionsToKill ; $nCount++ ) {
					$oSessProcessor->queryTerminateSession( $aSessions[ $nCount ] );
				}
			}
		}
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( !$oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$sForceLogout = $this->loadDP()->query( 'wpsf-forcelogout' );
		if ( $sForceLogout ) {

			switch ( $sForceLogout ) {
				case 1:
					$sMessage = _wpsf__( 'Your session has expired.' );
					break;

				case 2:
					$sMessage = _wpsf__( 'Your session was idle for too long.' );
					break;

				case 3:
					$sMessage = _wpsf__( 'Your session was locked to another IP Address.' );
					break;

				case 4:
					$sMessage = sprintf( _wpsf__( 'You do not currently have a %s user session.' ), $this->getController()
																										 ->getHumanName() );
					break;

				case 5:
					$sMessage = _wpsf__( 'An administrator has terminated this session.' );
					break;

				case 6:
					$sMessage = _wpsf__( 'Not a user.' );
					break;

				default:
					$sMessage = _wpsf__( 'Your session was terminated.' );
					break;
			}

			$sMessage .= '<br />'._wpsf__( 'Please login again.' );
			$oError->add( 'wpsf-forcelogout', $sMessage );
		}
		return $oError;
	}
}