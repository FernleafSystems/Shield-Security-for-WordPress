<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_UserManagement_Sessions extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );
		add_filter( 'auth_cookie_expiration', array( $this, 'setWordpressTimeoutCookieExpiration_Filter' ), 100, 1 );
		add_action( 'wp_login', array( $this, 'onWpLogin' ), 10, 2 );
		add_action( 'wp_loaded', array( $this, 'checkCurrentUser_Action' ), 1 ); // Check the current every page load.
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->enforceSessionLimits( $sUsername, $oUser );
	}

	/**
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUser_Action() {
		$oWp = $this->loadWp();
		$oWpUsers = $this->loadWpUsers();

		if ( $oWpUsers->isUserLoggedIn() ) {

			$nCode = $this->doVerifyCurrentSession();

			if ( is_admin() ) { // prevent any admin access on invalid Shield sessions.

				if ( $nCode > 0 ) {

					if ( $nCode == 7 ) {
						$oWpUsers->logoutUser( true );
						$this->addToAuditEntry(
							sprintf( 'Browser signature has changed for this user "%s" session. Redirecting request.', $oWpUsers->getCurrentWpUser()->user_login ),
							2,
							'um_session_browser_lock_redirect'
						);
						$oWp->redirectToLogin();
					}
					else if ( $nCode == 3 ) { // a session was used from the wrong IP. We just block it.
//						$this->loadWpUsers()->logoutUser( true ); // so as not to destroy the original, legitimate session
						$this->addToAuditEntry(
							sprintf( 'Access to an established user session from a new IP address "%s". Redirecting request.', $this->ip() ),
							2,
							'um_session_ip_lock_redirect'
						);
						$oWp->redirectToHome();
					}
					else {
						$this->addToAuditEntry(
							'Unable to verify the current User Session. Forcefully logging out session and redirecting to login.',
							2,
							'um_session_not_found_redirect'
						);
						$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => $nCode ) );
					}
				}
			}
			else if ( $nCode > 0 && !$oWp->isRestUrl() ) { // it's not admin, but the user looks logged into WordPress and not to Shield
				wp_set_current_user( 0 ); // ensures that is_user_logged_in() is false going forward.
			}
		}
	}

	/**
	 * @return int
	 */
	protected function doVerifyCurrentSession() {
		$oUser = $this->loadWpUsers()->getCurrentWpUser();

		if ( !is_object( $oUser ) || !( $oUser instanceof WP_User ) ) {
			$nForceLogOutCode = 6;
		}
		else {
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeature();
			$oDP = $this->loadDP();
			$nTime = $this->time();

			$oSess = $oFO->getSession();
			$nSessTimeout = $this->getSessionTimeoutInterval();
			$nSessIdleTimeout = $this->getOption( 'session_idle_timeout_interval', 0 )*HOUR_IN_SECONDS;

			$nForceLogOutCode = 0; // when it's == 0 it's a valid session

			if ( !$oSess ) {
				$nForceLogOutCode = 4;
			} // timeout interval
			else if ( $nSessTimeout > 0 && ( $nTime - $oSess->getLoggedInAt() > $nSessTimeout ) ) {
				$nForceLogOutCode = 1;
			} // idle timeout interval
			else if ( $nSessIdleTimeout > 0 && ( ( $nTime - $oSess->getLastActivityAt() ) > $nSessIdleTimeout ) ) {
				$nForceLogOutCode = 2;
			} // login ip address lock
			else if ( $this->isLockToIp() && ( md5( $this->ip() ) != $oSess->getIp() ) ) {
				$nForceLogOutCode = 3;
			}
			else if ( $this->isLockToBrowser() && ( $oSess->getBrowser() != md5( $oDP->getUserAgent() ) ) ) {
				$nForceLogOutCode = 7;
			}
		}

		return $nForceLogOutCode;
	}

	/**
	 * @param integer $nTimeout
	 * @return integer
	 */
	public function setWordpressTimeoutCookieExpiration_Filter( $nTimeout ) {
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		return ( ( $nSessionTimeoutInterval > 0 ) ? $nSessionTimeoutInterval : $nTimeout );
	}

	/**
	 * @return integer
	 */
	protected function getSessionTimeoutInterval() {
		return $this->getOption( 'session_timeout_interval' )*DAY_IN_SECONDS;
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sWpUsername
	 * @return array|boolean
	 */
	public function getActiveSessionRecordsForUsername( $sWpUsername ) {
		return $this->getActiveUserSessionRecords( $sWpUsername );
	}

	/**
	 * @return bool
	 */
	protected function isLockToIp() {
		return $this->getFeature()->getOptIs( 'session_lock_location', 'Y' );
	}

	/**
	 * @return bool
	 */
	protected function isLockToBrowser() {
		return $this->getFeature()->getOptIs( 'session_lock_browser', 'Y' );
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function enforceSessionLimits( $sUsername ) {

		$nSessionLimit = $this->getOption( 'session_username_concurrent_limit', 1 );
		if ( $nSessionLimit <= 0 ) {
			return true;
		}

		$aSessions = $this->getActiveSessionRecordsForUsername( $sUsername );
		$nSessionsToKill = count( $aSessions ) - $nSessionLimit;
		if ( $nSessionsToKill < 1 ) {
			return true;
		}

		$mResult = true;
		for ( $nCount = 0 ; $nCount < $nSessionsToKill ; $nCount++ ) {
			$mResult = $this->doTerminateUserSession( $aSessions[ $nCount ][ 'wp_username' ], $aSessions[ $nCount ][ 'session_id' ] );
		}
		return $mResult;
	}

	/**
	 * @return boolean
	 */

	/**
	 * @param string $sWpUsername
	 * @return array|bool
	 */
	public function getActiveUserSessionRecords( $sWpUsername = '' ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`deleted_at`	= '0'
				%s
			ORDER BY `last_activity_at` ASC
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			empty( $sWpUsername ) ? '' : "AND `wp_username` = '".esc_sql( $sWpUsername )."'"
		);
		return $this->selectCustom( $sQuery );
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( !$oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$sForceLogout = $this->loadDataProcessor()->FetchGet( 'wpsf-forcelogout' );
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