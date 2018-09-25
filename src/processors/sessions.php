<?php

if ( class_exists( 'ICWP_WPSF_Processor_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

class ICWP_WPSF_Processor_Sessions extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var int
	 */
	const DAYS_TO_KEEP = 30;

	/**
	 * @var ICWP_WPSF_SessionVO
	 */
	private $oCurrent;

	/**
	 * @param ICWP_WPSF_Processor_Sessions $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Sessions $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getSessionsTableName() );
	}

	public function run() {
		if ( $this->isReadyToRun() ) {
			add_action( 'clear_auth_cookie', array( $this, 'onWpClearAuthCookie' ), 0 ); //logout
			add_action( 'wp_loaded', array( $this, 'onWpLoaded' ), 0 );
			add_filter( 'login_message', array( $this, 'printLinkToAdmin' ) );
		}
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		if ( !$oUser instanceof WP_User ) {
			$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
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
		$this->activateUserSession( $this->loadWpUsers()->getUserById( $nUserId ) );
	}

	/**
	 */
	public function onWpClearAuthCookie() {
		$this->terminateCurrentSession();
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->loadWpUsers()->isUserLoggedIn() && !$this->loadWp()->isRest() ) {
			$this->autoAddSession();

			/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
			$oFO = $this->getMod();
			if ( $oFO->hasSession() ) {
				$this->getQueryUpdater()
					 ->updateLastActivity( $this->getCurrentSession() );
			}
		}
	}

	private function autoAddSession() {
		/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->hasSession() && $oFO->isAutoAddSessions() ) {
			$this->queryCreateSession(
				$this->loadWpUsers()->getCurrentWpUsername(),
				$oFO->getConn()->getSessionId( true )
			);
		}
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $sMessage
	 * @return string
	 * @throws Exception
	 */
	public function printLinkToAdmin( $sMessage = '' ) {
		/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
		$oFO = $this->getMod();
		$oWpUsers = $this->loadWpUsers();
		$sAction = $this->loadDP()->query( 'action' );

		if ( $oWpUsers->isUserLoggedIn() && $oFO->hasSession() && ( empty( $sAction ) || $sAction == 'login' ) ) {
			$sMessage = sprintf(
							'<p class="message">%s<br />%s</p>',
							_wpsf__( "You're already logged-in." ).sprintf(
								' <span style="white-space: nowrap">(%s)</span>',
								$oWpUsers->getCurrentWpUsername() ),
							( $oWpUsers->getCurrentUserLevel() >= 2 ) ? sprintf( '<a href="%s">%s</a>',
								$this->loadWp()->getUrl_WpAdmin(),
								_wpsf__( "Go To Admin" ).' &rarr;' ) : '' )
						.$sMessage;
		}
		return $sMessage;
	}

	/**
	 * @param WP_User $oUser
	 * @return boolean
	 */
	private function activateUserSession( $oUser ) {
		if ( !$this->isLoginCaptured() && $oUser instanceof WP_User ) {
			$this->setLoginCaptured();
			// If they have a currently active session, terminate it (i.e. we replace it)
			$oSession = $this->queryGetSession( $oUser->user_login, $this->getSessionId() );
			if ( !empty( $oSession ) ) {
				$this->queryTerminateSession( $oSession );
				$this->oCurrent = null;
			}

			$this->queryCreateSession( $oUser->user_login, $this->getSessionId() );
		}
		return true;
	}

	/**
	 * @return string
	 */
	private function getSessionId() {
		return $this->getController()->getSessionId();
	}

	/**
	 * @return boolean
	 */
	protected function terminateCurrentSession() {
		if ( !$this->loadWpUsers()->isUserLoggedIn() ) {
			return false;
		}

		$mResult = $this->queryTerminateSession( $this->getCurrentSession() );
		$this->getController()->clearSession();
		$this->oCurrent = null;
		return $mResult;
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
			browser varchar(32) NOT NULL DEFAULT '',
			logged_in_at int(15) NOT NULL DEFAULT 0,
			last_activity_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			last_activity_uri text NOT NULL DEFAULT '',
			secadmin_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			login_intent_expires_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return ICWP_WPSF_SessionVO|null
	 */
	public function getCurrentSession() {
		if ( is_null( $this->oCurrent ) ) {
			$this->oCurrent = $this->loadCurrentSession();
		}
		return $this->oCurrent;
	}

	/**
	 * @return ICWP_WPSF_SessionVO|null
	 */
	public function loadCurrentSession() {
		$oSession = null;
		$oWpUsers = $this->loadWpUsers();
		if ( did_action( 'init' ) && $oWpUsers->isUserLoggedIn() ) {
			$oUser = $oWpUsers->getCurrentWpUser();
			if ( $oUser instanceof WP_User ) {
				$oSession = $this->queryGetSession( $oUser->user_login, $this->getSessionId() );
			}
		}
		return $oSession;
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Insert
	 */
	public function getQueryInserter() {
		$this->queryRequireLib( 'insert.php' );
		$oQ = new ICWP_WPSF_Query_Sessions_Insert();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Delete
	 */
	public function getQueryDeleter() {
		$this->queryRequireLib( 'delete.php' );
		$oQ = new ICWP_WPSF_Query_Sessions_Delete();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Select
	 */
	public function getQuerySelector() {
		$this->queryRequireLib( 'select.php' );
		$oQ = new ICWP_WPSF_Query_Sessions_Select();
		return $oQ->setTable( $this->getTableName() )
				  ->setResultsAsVo( true );
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Update
	 */
	public function getQueryUpdater() {
		$this->queryRequireLib( 'update.php' );
		$oQ = new ICWP_WPSF_Query_Sessions_Update();
		return $oQ->setTable( $this->getTableName() );
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return bool
	 */
	protected function queryCreateSession( $sUsername, $sSessionId ) {
		if ( empty( $sUsername ) ) {
			return null;
		}

		$bSuccess = $this->getQueryInserter()
						 ->create( $sUsername, $sSessionId );
		if ( $bSuccess ) {
			$this->doStatIncrement( 'user.session.start' );
		}
		return $bSuccess;
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return ICWP_WPSF_SessionVO|null
	 */
	protected function queryGetSession( $sUsername, $sSessionId ) {
		return $this->getQuerySelector()
					->retrieveUserSession( $sUsername, $sSessionId );
	}

	/**
	 * @param ICWP_WPSF_SessionVO $oSession
	 * @return bool|int
	 */
	public function queryTerminateSession( $oSession ) {
		if ( empty( $oSession ) ) {
			return true;
		}
		$this->doStatIncrement( 'user.session.terminate' );

		return $this->getQueryDeleter()
					->deleteById( $oSession->getId() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getMod()->getDef( 'sessions_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return DAY_IN_SECONDS*self::DAYS_TO_KEEP;
	}

	/**
	 * @return string
	 */
	protected function queryGetDir() {
		return parent::queryGetDir().'sessions/';
	}
}