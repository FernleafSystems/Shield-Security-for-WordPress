<?php

if ( class_exists( 'ICWP_WPSF_Processor_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/basedb.php' );

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

class ICWP_WPSF_Processor_Sessions extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var int
	 */
	const DAYS_TO_KEEP = 30;

	/**
	 * @var Session\EntryVO
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
				$oFO->getConn()->getSessionId( true ),
				$this->loadWpUsers()->getCurrentWpUsername()
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
		$sAction = $this->loadRequest()->query( 'action' );

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
			$oSession = $this->queryGetSession( $this->getSessionId(), $oUser->user_login );
			if ( !empty( $oSession ) ) {
				$this->queryTerminateSession( $oSession );
				$this->clearCurrentSession();
			}

			$this->queryCreateSession( $this->getSessionId(), $oUser->user_login );
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
		$this->clearCurrentSession();
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
			li_code_email varchar(6) NOT NULL DEFAULT '',
			login_intent_expires_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			secadmin_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
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
	 * @return $this
	 */
	public function clearCurrentSession() {
		$this->oCurrent = null;
		return $this;
	}

	/**
	 * @return Session\EntryVO|null
	 */
	public function loadCurrentSession() {
		$oSession = null;
		if ( did_action( 'init' ) ) {
			$oSession = $this->queryGetSession(
				$this->getSessionId(),
				$this->loadWpUsers()->getCurrentWpUsername()
			);
		}
		return $oSession;
	}

	/**
	 * @return Session\Insert
	 */
	public function getQueryInserter() {
		return ( new Session\Insert() )
			->setTable( $this->getTableName() );
	}

	/**
	 * @return Session\Delete
	 */
	public function getQueryDeleter() {
		return ( new Session\Delete() )
			->setTable( $this->getTableName() );
	}

	/**
	 * @return Session\Select
	 */
	public function getQuerySelector() {
		return ( new Session\Select() )
			->setTable( $this->getTableName() )
			->setResultsAsVo( true );
	}

	/**
	 * @return Session\Update
	 */
	public function getQueryUpdater() {
		return ( new Session\Update() )
			->setTable( $this->getTableName() );
	}

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	protected function queryCreateSession( $sSessionId, $sUsername ) {
		if ( empty( $sSessionId ) || empty( $sUsername ) ) {
			return null;
		}

		$bSuccess = $this->getQueryInserter()->create( $sSessionId, $sUsername );
		if ( $bSuccess ) {
			$this->doStatIncrement( 'user.session.start' );
		}
		return $bSuccess;
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return Session\EntryVO|null
	 */
	protected function queryGetSession( $sSessionId, $sUsername = '' ) {
		return $this->getQuerySelector()
					->retrieveUserSession( $sSessionId, $sUsername );
	}

	/**
	 * @param Session\EntryVO $oSession
	 * @return bool|int
	 */
	public function queryTerminateSession( $oSession ) {
		if ( empty( $oSession ) ) {
			return true;
		}
		$this->doStatIncrement( 'user.session.terminate' );

		return $this->getQueryDeleter()
					->deleteEntry( $oSession );
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
}