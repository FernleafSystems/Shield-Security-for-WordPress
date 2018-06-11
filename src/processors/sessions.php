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
	 * @var int
	 */
	private $nSessionAlreadyCreatedUserId = 0;

	/**
	 * @param ICWP_WPSF_Processor_Sessions $oModCon
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Sessions $oModCon ) {
		parent::__construct( $oModCon, $oModCon->getSessionsTableName() );
	}

	public function run() {
		if ( $this->isReadyToRun() ) {
			add_action( 'set_logged_in_cookie', array( $this, 'onWpSetLoggedInCookie' ), 5, 4 ); //login
			add_action( 'clear_auth_cookie', array( $this, 'onWpClearAuthCookie' ), 0 ); //logout
			add_action( 'wp_loaded', array( $this, 'onWpLoaded' ), 0 );
			add_filter( 'login_message', array( $this, 'printLinkToAdmin' ) );
		}
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$oUser = $this->loadWpUsers()
					  ->getUserById( $nUserId );
		if ( $oUser instanceof WP_User && !$this->isSessionAlreadyCreatedForUser( $oUser ) ) {
			$this->activateUserSession( $oUser->user_login, $oUser );
		}
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpClearAuthCookie() {
		$this->terminateCurrentSession();
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		$this->activateUserSession( $sUsername, $oUser );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( $this->loadWpUsers()->isUserLoggedIn() && !$this->loadWp()->isRest() ) {
			$this->autoAddSession();
			$this->queryUpdateSessionLastActivity();
		}
	}

	private function autoAddSession() {
		/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
		$oFO = $this->getFeature();
		if ( !$oFO->hasSession() && $oFO->isAutoAddSessions() ) {
			$this->queryCreateSession(
				$oWpUsers = $this->loadWpUsers()->getCurrentWpUser()->user_login,
				$oFO->getConn()->getSessionId( true )
			);
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	private function isSessionAlreadyCreatedForUser( $oUser ) {
		return $this->nSessionAlreadyCreatedUserId > 0 && $this->nSessionAlreadyCreatedUserId == $oUser->ID;
	}

	/**
	 * Only show Go To Admin link for Authors and above.
	 * @param string $sMessage
	 * @return string
	 * @throws Exception
	 */
	public function printLinkToAdmin( $sMessage = '' ) {
		$oWpUsers = $this->loadWpUsers();
		if ( $oWpUsers->isUserLoggedIn() ) {
			$oUser = $oWpUsers->getCurrentWpUser();
			/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
			$oFO = $this->getFeature();
			if ( $oFO->hasSession() ) {
				$sMessage = sprintf(
								'<p class="message">%s<br />%s</p>',
								_wpsf__( "You're already logged-in." ).sprintf(
									' <span style="white-space: nowrap">(%s)</span>',
									$oUser->get( 'user_login' ) ),
								( $oWpUsers->getCurrentUserLevel() >= 2 ) ? sprintf( '<a href="%s">%s</a>',
									$this->loadWp()->getUrl_WpAdmin(),
									_wpsf__( "Go To Admin" ).' &rarr;' ) : '' )
							.$sMessage;
			}
		}
		return $sMessage;
	}

	/**
	 * @param string  $sUsername
	 * @param WP_User $oUser
	 * @return boolean
	 */
	private function activateUserSession( $sUsername, $oUser ) {
		if ( !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}

		if ( $this->isSessionAlreadyCreatedForUser( $oUser ) ) {
			return true;
		}

		// If they have a currently active session, terminate it (i.e. we replace it)
		$oSession = $this->queryGetSession( $sUsername, $this->getSessionId() );
		if ( !empty( $oSession ) ) {
			$this->queryTerminateSession( $oSession );
		}
		$this->queryCreateSession( $sUsername, $this->getSessionId() );
		$this->nSessionAlreadyCreatedUserId = $oUser->ID;
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
		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		if ( empty( $oUser ) || !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}
//		$this->getCurrentUserMeta()->login_browser = '';

		$oSession = $this->getCurrentSession();
		$mResult = $this->queryTerminateSession( $oSession );
		$this->getController()->clearSession();
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
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return ICWP_WPSF_SessionVO|null
	 */
	protected function queryGetSession( $sUsername, $sSessionId ) {
		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_retrieve.php' );

		$oRetrieve = new ICWP_WPSF_Query_Sessions_Retrieve();
		return $oRetrieve->setTable( $this->getTableName() )
						 ->retrieveUserSession( $sUsername, $sSessionId );
	}

	/**
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function queryGetActiveSessions() {
		return $this->getSessionRetriever()->all();
	}

	/**
	 * @param string $sWpUsername
	 * @return ICWP_WPSF_SessionVO[]
	 */
	public function queryGetActiveSessionsForUsername( $sWpUsername ) {
		return $this->getSessionRetriever()->retrieveForUsername( $sWpUsername );
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return null|ICWP_WPSF_SessionVO
	 */
	public function queryCreateSession( $sUsername, $sSessionId ) {
		if ( empty( $sUsername ) ) {
			return null;
		}

		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_create.php' );
		$oCreator = new ICWP_WPSF_Query_Sessions_Create();
		$bSuccess = $oCreator->setTable( $this->getTableName() )
							 ->create( $sUsername, $sSessionId );
		if ( $bSuccess ) {
			$this->doStatIncrement( 'user.session.start' );
		}
		return $bSuccess ? $this->queryGetSession( $sUsername, $sSessionId ) : null;
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

		return $this->getSessionTerminator()
					->setTable( $this->getTableName() )
					->forUserSession( $oSession );
	}

	/**
	 */
	protected function queryUpdateSessionLastActivity() {
		/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->hasSession() ) {
			$this->getSessionUpdater()->updateLastActivity( $this->getCurrentSession() );
		}
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Retrieve
	 */
	public function getSessionRetriever() {
		require_once( $this->getQueryDir().'sessions_retrieve.php' );
		$oRetrieve = new ICWP_WPSF_Query_Sessions_Retrieve();
		return $oRetrieve->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Terminate
	 */
	public function getSessionTerminator() {
		require_once( $this->getQueryDir().'sessions_terminate.php' );
		$oRetrieve = new ICWP_WPSF_Query_Sessions_Terminate();
		return $oRetrieve->setTable( $this->getTableName() );
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Update
	 */
	public function getSessionUpdater() {
		require_once( $this->getQueryDir().'sessions_update.php' );
		$oUpdate = new ICWP_WPSF_Query_Sessions_Update();
		return $oUpdate->setTable( $this->getTableName() );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDef( 'sessions_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}

	/**
	 * @return int
	 */
	protected function getAutoExpirePeriod() {
		return DAY_IN_SECONDS*self::DAYS_TO_KEEP;
	}
}