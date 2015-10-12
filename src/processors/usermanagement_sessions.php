<?php

if ( !class_exists( 'ICWP_WPSF_Processor_UserManagement_Sessions', false ) ):

require_once( dirname(__FILE__).ICWP_DS.'basedb.php' );

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
	 * @param ICWP_WPSF_FeatureHandler_UserManagement  $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_UserManagement $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getUserSessionsTableName() );
	}

	public function run() {

		add_filter( 'wp_login_errors', array( $this, 'addLoginMessage' ) );

		// When we know user has successfully authenticated and we activate the session entry in the database
		add_action( 'wp_login', array( $this, 'activateUserSession' ), 10, 2 );

		add_action( 'wp_logout', array( $this, 'onWpLogout' ) );

		add_filter( 'auth_cookie_expiration', array( $this, 'setWordpressTimeoutCookieExpiration_Filter' ), 100, 1 );

		// Check the current logged-in user every page load.
		add_action( 'wp_loaded', array( $this, 'checkCurrentUser_Action' ), 1 );
	}

	/**
	 * @param string $sUsername
	 * @param WP_User $oUser
	 *
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
		$this->setSessionCookie();
		$this->doLimitUserSession( $sUsername );
		return true;
	}

	/**
	 * Should be hooked to 'init' so we have is_user_logged_in()
	 */
	public function checkCurrentUser_Action() {

		if ( is_user_logged_in() ) {

			if ( is_admin() ) {
				$this->doVerifyCurrentSession();
			}

			// At this point session is validated
			$oDp = $this->loadDataProcessor();
			$oWp = $this->loadWpFunctionsProcessor();
			if ( $oWp->getIsLoginUrl() && $this->loadWpUsersProcessor()->isUserAdmin() ) {
				$sLoginAction = $oDp->FetchGet( 'action' );
				if ( !in_array( $sLoginAction, array( 'logout', 'postpass' ) ) ) {
					$oWp->redirectToAdmin();
				}
			}

			// always track activity
			$oUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
			$this->updateSessionLastActivity( $oUser );
		}
	}

	/**
	 * @param WP_User $oUser
	 *
	 * @return boolean
	 */
	protected function updateSessionLastActivity( $oUser ) {
		if ( !is_object( $oUser ) || ! ( $oUser instanceof WP_User ) ) {
			return false;
		}

		$aNewData = array(
			'last_activity_at'	=> $this->time(),
			'last_activity_uri'	=> $this->loadDataProcessor()->FetchServer( 'REQUEST_URI' )
		);
		return $this->updateSession( $this->getSessionId(), $oUser->get( 'user_login' ), $aNewData );
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @param array $aUpdateData
	 *
	 * @return boolean
	 */
	protected function updateSession( $sSessionId, $sUsername, $aUpdateData ) {
		$aWhere = array(
			'session_id'	=> $sSessionId,
			'wp_username'	=> $sUsername,
			'deleted_at'	=> 0
		);
		$mResult = $this->updateRowsWhere( $aUpdateData, $aWhere );
		return $mResult;
	}

	/**
	 * If it cannot verify current user, will forcefully log them out and redirect to login
	 *
	 * @return bool
	 */
	protected function doVerifyCurrentSession() {
		$oWpUsers = $this->loadWpUsersProcessor();
		$oUser = $this->loadWpUsersProcessor()->getCurrentWpUser();

		if ( !is_object( $oUser ) || ! ( $oUser instanceof WP_User ) ) {
			$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => 6 ) );
		}

		$aLoginSessionData = $this->getUserSessionRecord( $oUser->get( 'user_login' ), $this->getSessionId() );
		if ( !$aLoginSessionData ) {
			$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => 4 ) );
		}

		// check timeout interval
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		if ( $nSessionTimeoutInterval > 0 && ( $this->time() - $aLoginSessionData['logged_in_at'] > $nSessionTimeoutInterval ) ) {
			$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => 1 ) );
		}

		// check idle timeout interval
		$nSessionIdleTimeoutInterval = $this->getOption( 'session_idle_timeout_interval', 0 ) * HOUR_IN_SECONDS;
		if ( intval($nSessionIdleTimeoutInterval) > 0 && ( ($this->time() - $aLoginSessionData['last_activity_at']) > $nSessionIdleTimeoutInterval ) ) {
			$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => 2 ) );
		}

		// check login ip address
		$fLockToIp = $this->getIsOption( 'session_lock_location', 'Y' );
		$sVisitorIp = $this->loadDataProcessor()->getVisitorIpAddress( true );
		if ( $fLockToIp && $sVisitorIp != $aLoginSessionData['ip'] ) {
			$oWpUsers->forceUserRelogin( array( 'wpsf-forcelogout' => 3 ) );
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function getSessionId() {
		if ( empty( $this->sSessionId ) ) {
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeatureOptions();
			$this->sSessionId = $this->loadDataProcessor()->FetchCookie( $oFO->getUserSessionCookieName() );
			if ( empty( $this->sSessionId ) ) {
				$this->sSessionId = $oFO->getController()->getSessionId();
				$this->setSessionCookie();
			}
		}
		return $this->sSessionId;
	}

	/**
	 * @param integer $nTimeout
	 * @return integer
	 */
	public function setWordpressTimeoutCookieExpiration_Filter( $nTimeout ) {
		$nSessionTimeoutInterval = $this->getSessionTimeoutInterval();
		return ( ( $nSessionTimeoutInterval > 0 )? $nSessionTimeoutInterval : $nTimeout );
	}

	/**
	 * @return integer
	 */
	protected function getSessionTimeoutInterval( ) {
		return $this->getOption( 'session_timeout_interval' ) * DAY_IN_SECONDS;
	}

	/**
	 */
	protected function setSessionCookie() {
		if ( $this->getSessionTimeoutInterval() > 0 ) {
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeatureOptions();
			$this->loadDataProcessor()->setCookie(
				$oFO->getUserSessionCookieName(),
				$this->getSessionId(),
				$this->getSessionTimeoutInterval()
			);
		}
	}

	/**
	 * Checks for and gets a user session.
	 *
	 * @param string $sWpUsername
	 * @return array|boolean
	 */
	public function getActiveSessionRecordsForUsername( $sWpUsername ) {
		return $this->getActiveUserSessionRecords( $sWpUsername );
	}

	/**
	 * Checks for and gets a user session.
	 *
	 * @param string $sUsername
	 * @param string $sSessionId
	 *
	 * @return array|bool
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
			return $mResult[0];
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
	 *
	 * @return bool|int
	 */
	protected function doAddNewActiveUserSessionRecord( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}

		$sSessionId = $this->getSessionId();

		$oDp = $this->loadDataProcessor();
		// Add new session entry
		// set attempts = 1 and then when we know it's a valid login, we zero it.
		// First set any other entries for the given user to be deleted.
		$aNewData = array();
		$aNewData[ 'session_id' ]			= $sSessionId;
		$aNewData[ 'ip' ]			    	= $oDp->getVisitorIpAddress( true );
		$aNewData[ 'wp_username' ]			= $sUsername;
		$aNewData[ 'login_attempts' ]		= 0;
		$aNewData[ 'pending' ]				= 0;
		$aNewData[ 'logged_in_at' ]			= $this->time();
		$aNewData[ 'last_activity_at' ]		= $this->time();
		$aNewData[ 'last_activity_uri' ]	= $oDp->FetchServer( 'REQUEST_URI' );
		$aNewData[ 'created_at' ]			= $this->time();
		$mResult = $this->insertData( $aNewData );

		return $mResult;
	}

	/**
	 * @param string $sUsername
	 *
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
		for( $nCount = 0; $nCount < $nSessionsToKill; $nCount++ ) {
			$mResult = $this->doTerminateUserSession( $aSessions[$nCount]['wp_username'], $aSessions[$nCount]['session_id'] );
		}
		return $mResult;
	}

	/**
	 * @return boolean
	 */
	protected function doTerminateCurrentUserSession() {
		$oUser = $this->loadWpUsersProcessor()->getCurrentWpUser();
		if ( empty( $oUser ) || !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeatureOptions();
		$mResult = $this->doTerminateUserSession( $oUser->get( 'user_login' ), $this->getSessionId() );
		$this->loadDataProcessor()->setDeleteCookie( $oFO->getUserSessionCookieName() );
		return $mResult;
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @param bool $bHardDelete
	 *
	 * @return bool|int
	 */
	protected function doTerminateUserSession( $sUsername, $sSessionId, $bHardDelete = true ) {

		$aWhere = array(
			'session_id'	=> $sSessionId,
			'wp_username'	=> $sUsername,
			'deleted_at'	=> 0
		);

		if ( $bHardDelete ) {
			return $this->loadDbProcessor()->deleteRowsFromTableWhere( $this->getTableName(), $aWhere );
		}

		$aNewData = array( 'deleted_at'	=> $this->time() );
		return $this->updateRowsWhere( $aNewData, $aWhere );
	}

	/**
	 * @param string $sWpUsername
	 *
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
			empty( $sWpUsername ) ? '' : "AND `wp_username` = '".esc_sql( $sWpUsername )."'"
		);
		return $this->selectCustom( $sQuery );
	}

	/**
	 * @param WP_Error $oError
	 * @return WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( ! $oError instanceof WP_Error ) {
			$oError = new WP_Error();
		}

		$sForceLogout = $this->loadDataProcessor()->FetchGet( 'wpsf-forcelogout' );
		if ( $sForceLogout ) {

			switch( $sForceLogout ) {
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
					$sMessage = sprintf( _wpsf__( 'You do not currently have a %s user session.' ), $this->getController()->getHumanName() );
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

	/**
	 * @return string
	 */
	public function getCreateTableSql() {
		$sSqlTables = "CREATE TABLE IF NOT EXISTS `%s` (
			`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(32) NOT NULL DEFAULT '',
			`wp_username` VARCHAR(255) NOT NULL DEFAULT '',
			`ip` VARCHAR(40) NOT NULL DEFAULT '0',
			`logged_in_at` INT(15) NOT NULL DEFAULT '0',
			`last_activity_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
			`last_activity_uri` text NOT NULL DEFAULT '',
			`used_mfa` INT(1) NOT NULL DEFAULT '0',
			`pending` TINYINT(1) NOT NULL DEFAULT '0',
			`login_attempts` INT(1) NOT NULL DEFAULT '0',
			`created_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
			`deleted_at` INT(15) UNSIGNED NOT NULL DEFAULT '0',
 			PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		return sprintf( $sSqlTables, $this->getTableName() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		return $this->getOption( 'user_sessions_table_columns' );
	}

	/**
	 * @param integer $nTime - number of seconds back from now to look
	 * @return array|boolean
	 */
	public function getPendingOrFailedUserSessionRecordsSince( $nTime = 0 ) {

		$nTime = ( $nTime <= 0 ) ? 2*DAY_IN_SECONDS : $nTime;

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

	/**
	 * This is hooked into a cron in the base class and overrides the parent method.
	 *
	 * It'll delete everything older than 24hrs.
	 */
	public function cleanupDatabase() {
		if ( !$this->getTableExists() ) {
			return;
		}
		$nTimeStamp = $this->time() - (DAY_IN_SECONDS * $this->nDaysToKeepLog);
		$this->deleteAllRowsOlderThan( $nTimeStamp );
	}
}
endif;