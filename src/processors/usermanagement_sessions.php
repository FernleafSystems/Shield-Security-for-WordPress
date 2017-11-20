<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'basedb.php' );

class ICWP_WPSF_Processor_UserManagement_Sessions extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var string
	 */
	protected $nDaysToKeepLog = 30;

	/**
	 * @var string
	 */
	protected $sSessionId;

	/**
	 * @param ICWP_WPSF_FeatureHandler_UserManagement $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_UserManagement $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getUserSessionsTableName() );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		$this->setAutoExpirePeriod( DAY_IN_SECONDS * $this->nDaysToKeepLog );
	}

	public function run() {
		if ( !$this->readyToRun() ) {
			return;
		}

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );

		// When we know user has successfully authenticated and we activate the session entry in the database
		add_action( 'wp_login', array( $this, 'activateUserSession' ), 10, 2 );

		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		add_filter( 'auth_cookie_expiration', array( $this, 'setWordpressTimeoutCookieExpiration_Filter' ), 100, 1 );

		// Check the current logged-in user every page load.
		add_action( 'wp_loaded', array( $this, 'checkCurrentUser_Action' ), 1 );
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 * @return boolean
	 */
	public function activateUserSession( $sUsername, $oUser ) {
		if ( !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}

		// If they have a currently active session, terminate it (i.e. we replace it)
		$aSession = $this->getUserSessionRecord( $sUsername, $this->getSessionId() );
		if ( !empty( $aSession ) ) {
			$this->doTerminateUserSession( $sUsername, $this->getSessionId() );
		}

		$this->doAddNewActiveUserSessionRecord( $sUsername );
		$this->doLimitUserSession( $sUsername );
		return true;
	}

	/**
	 * @return bool
	 */
	public function getCurrentUserHasValidSession() {
		return ( $this->doVerifyCurrentSession() == 0 );
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

					if ( $nCode == 3 ) { // a session was used from the wrong IP. We just block it.
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

			// Auto-redirect to admin: UNUSED
			if ( $oWp->isRequestLoginUrl() && $this->loadWpUsers()->isUserAdmin() ) {
				$sLoginAction = $this->loadDataProcessor()->FetchGet( 'action' );
				if ( !in_array( $sLoginAction, array( 'logout', 'postpass' ) ) ) {
					// $oWp->redirectToAdmin();
				}
			}

			// always track last activity
			$this->updateSessionLastActivity( $oWpUsers->getCurrentWpUser() );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return boolean
	 */
	protected function updateSessionLastActivity( $oUser ) {
		if ( !is_object( $oUser ) || !( $oUser instanceof WP_User ) ) {
			return false;
		}

		$aNewData = array(
			'last_activity_at'  => $this->time(),
			'last_activity_uri' => $this->loadDataProcessor()->FetchServer( 'REQUEST_URI' )
		);
		return $this->updateSession( $this->getSessionId(), $oUser->get( 'user_login' ), $aNewData );
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @param array  $aUpdateData
	 * @return boolean
	 */
	protected function updateSession( $sSessionId, $sUsername, $aUpdateData ) {
		$aWhere = array(
			'session_id'  => $sSessionId,
			'wp_username' => $sUsername,
			'deleted_at'  => 0
		);
		$mResult = $this->updateRowsWhere( $aUpdateData, $aWhere );
		return $mResult;
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

			$aLoginSessionData = $this->getUserSessionRecord( $oUser->get( 'user_login' ), $this->getSessionId() );
			$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
			$nSessionIdleTimeoutInterval = $this->getOption( 'session_idle_timeout_interval', 0 ) * HOUR_IN_SECONDS;
			$bLockToIp = $this->getIsOption( 'session_lock_location', 'Y' );
			$sVisitorIp = $this->ip();

			$nForceLogOutCode = 0; // when it's == 0 it's a valid session

			if ( !is_object( $oUser ) || !( $oUser instanceof WP_User ) ) {
				$nForceLogOutCode = 6;

			} // session?
			else if ( !$aLoginSessionData ) {
				$nForceLogOutCode = 4;

			} // timeout interval
			else if ( $nSessionTimeoutInterval > 0 && ( $this->time() - $aLoginSessionData[ 'logged_in_at' ] > $nSessionTimeoutInterval ) ) {
				$nForceLogOutCode = 1;

			} // idle timeout interval
			else if ( $nSessionIdleTimeoutInterval > 0 && ( ( $this->time() - $aLoginSessionData[ 'last_activity_at' ] ) > $nSessionIdleTimeoutInterval ) ) {
				$nForceLogOutCode = 2;

			} // login ip address lock
			else if ( $bLockToIp && $sVisitorIp != $aLoginSessionData[ 'ip' ] ) {
				$nForceLogOutCode = 3;
			}
		}

		return $nForceLogOutCode;
	}

	/**
	 * @return string
	 */
	public function getSessionId() {
		if ( empty( $this->sSessionId ) ) {
			$this->sSessionId = $this->getController()->getSessionId();
		}
		return $this->sSessionId;
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
		return $this->getOption( 'session_timeout_interval' ) * DAY_IN_SECONDS;
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
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return array|false
	 */
	protected function getUserSessionRecord( $sUsername, $sSessionId ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username`		= '%s'
				AND `session_id`	= '%s'
				AND `deleted_at`	= '0'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			esc_sql( $sUsername ),
			esc_sql( $sSessionId )
		);

		$mResult = $this->selectCustom( $sQuery );
		if ( is_array( $mResult ) && count( $mResult ) == 1 ) {
			return $mResult[ 0 ];
		}
		return false;
	}

	/**
	 */
	public function onWpLogout() {
		$this->doTerminateCurrentUserSession();
	}

	/**
	 * @param string $sUsername
	 * @return bool|int
	 */
	protected function doAddNewActiveUserSessionRecord( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}

		$nTimeStamp = $this->time();

		// Add new session entry
		// set attempts = 1 and then when we know it's a valid login, we zero it.
		// First set any other entries for the given user to be deleted.
		$aNewData = array();
		$aNewData[ 'session_id' ] = $this->getSessionId();
		$aNewData[ 'ip' ] = $this->ip();
		$aNewData[ 'wp_username' ] = $sUsername;
		$aNewData[ 'login_attempts' ] = 0;
		$aNewData[ 'pending' ] = 0;
		$aNewData[ 'logged_in_at' ] = $nTimeStamp;
		$aNewData[ 'last_activity_at' ] = $nTimeStamp;
		$aNewData[ 'last_activity_uri' ] = $this->loadDataProcessor()->FetchServer( 'REQUEST_URI' );
		$aNewData[ 'created_at' ] = $nTimeStamp;
		$mResult = $this->insertData( $aNewData );

		$this->doStatIncrement( 'user.session.start' );
		return $mResult;
	}

	/**
	 * @param string $sUsername
	 * @return boolean
	 */
	protected function doLimitUserSession( $sUsername ) {

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
		for ( $nCount = 0; $nCount < $nSessionsToKill; $nCount++ ) {
			$mResult = $this->doTerminateUserSession( $aSessions[ $nCount ][ 'wp_username' ], $aSessions[ $nCount ][ 'session_id' ] );
		}
		return $mResult;
	}

	/**
	 * @return boolean
	 */
	protected function doTerminateCurrentUserSession() {
		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		if ( empty( $oUser ) || !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}
		$mResult = $this->doTerminateUserSession( $oUser->get( 'user_login' ), $this->getSessionId() );
		$this->getController()->clearSession();
		return $mResult;
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @param bool   $bHardDelete
	 * @return bool|int
	 */
	protected function doTerminateUserSession( $sUsername, $sSessionId, $bHardDelete = true ) {
		$this->doStatIncrement( 'user.session.terminate' );

		$aWhere = array(
			'session_id'  => $sSessionId,
			'wp_username' => $sUsername,
			'deleted_at'  => 0
		);

		if ( $bHardDelete ) {
			return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTableName(), $aWhere );
		}

		$aNewData = array( 'deleted_at' => $this->time() );
		return $this->updateRowsWhere( $aNewData, $aWhere );
	}

	/**
	 * @param string $sWpUsername
	 * @return array|bool
	 */
	public function getActiveUserSessionRecords( $sWpUsername = '' ) {

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`pending`			= '0'
				AND `deleted_at`	= '0'
				%s
			ORDER BY `last_activity_at` ASC
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			empty( $sWpUsername ) ? '' : "AND `wp_username` = '" . esc_sql( $sWpUsername ) . "'"
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

			$sMessage .= '<br />' . _wpsf__( 'Please login again.' );
			$oError->add( 'wpsf-forcelogout', $sMessage );
		}
		return $oError;
	}

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE %s (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id varchar(32) NOT NULL DEFAULT '',
			wp_username varchar(255) NOT NULL DEFAULT '',
			ip varchar(40) NOT NULL DEFAULT '0',
			logged_in_at int(15) NOT NULL DEFAULT 0,
			last_activity_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			last_activity_uri text NOT NULL DEFAULT '',
			used_mfa int(1) NOT NULL DEFAULT 0,
			pending tinyint(1) NOT NULL DEFAULT 0,
			login_attempts int(1) NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDefinition( 'user_sessions_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @param integer $nTime - number of seconds back from now to look
	 * @return array|boolean
	 */
	public function getPendingOrFailedUserSessionRecordsSince( $nTime = 0 ) {

		$nTime = ( $nTime <= 0 ) ? 2 * DAY_IN_SECONDS : $nTime;

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`pending`			= '1'
				AND `deleted_at`	= '0'
				AND `created_at`	> '%s'
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTableName(),
			( $this->time() - $nTime )
		);

		return $this->selectCustom( $sQuery );
	}
}