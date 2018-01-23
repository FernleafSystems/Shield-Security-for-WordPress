<?php

if ( class_exists( 'ICWP_WPSF_Processor_Sessions', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'basedb.php' );

class ICWP_WPSF_Processor_Sessions extends ICWP_WPSF_BaseDbProcessor {

	/**
	 * @var string
	 */
	protected $nDaysToKeepLog = 30;

	/**
	 * @param ICWP_WPSF_Processor_Sessions $oFeatureOptions
	 */
	public function __construct( ICWP_WPSF_FeatureHandler_Sessions $oFeatureOptions ) {
		parent::__construct( $oFeatureOptions, $oFeatureOptions->getSessionsTableName() );
	}

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
		$this->setAutoExpirePeriod( DAY_IN_SECONDS*$this->nDaysToKeepLog );
	}

	public function run() {
		if ( $this->readyToRun() ) {
			add_action( 'wp_login', array( $this, 'onWpLogin' ), 5, 2 );
			add_action( 'wp_logout', array( $this, 'onWpLogout' ), 0 );
			add_action( 'wp_loaded', array( $this, 'onWpLoaded' ), 1 );
			add_filter( 'login_message', array( $this, 'printLinkToAdmin' ) );
		}
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
	public function onWpLogout() {
		$this->terminateCurrentSession();
	}

	/**
	 */
	public function onWpLoaded() {
		$this->queryUpdateSessionLastActivity();
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

		// If they have a currently active session, terminate it (i.e. we replace it)
		$oSession = $this->queryGetSession( $sUsername, $this->getSessionId() );
		if ( !empty( $oSession ) ) {
			$this->queryTerminateSession( $oSession );
		}
		$this->queryCreateSession( $sUsername, $this->getSessionId() );
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
			ip varchar(32) NOT NULL DEFAULT '0',
			browser varchar(32) NOT NULL DEFAULT '',
			logged_in_at int(15) NOT NULL DEFAULT 0,
			last_activity_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			last_activity_uri text NOT NULL DEFAULT '',
			created_at int(15) UNSIGNED NOT NULL DEFAULT 0,
			deleted_at int(15) UNSIGNED NOT NULL DEFAULT 0,
 			PRIMARY KEY  (id)
		) %s;";
		return sprintf( $sSqlTables, $this->getTableName(), $this->loadDbProcessor()->getCharCollate() );
	}

	/**
	 * @return SessionVO|null
	 */
	public function getCurrentSession() {
		/** @var ICWP_WPSF_FeatureHandler_Sessions $oFO */
		$oFO = $this->getFeature();
		return $oFO->getSession();
	}

	/**
	 * @return SessionVO|null
	 */
	public function loadCurrentSession() {
		$oSession = null;
		$oUser = $this->loadWpUsers()->getCurrentWpUser();
		if ( $oUser instanceof WP_User ) {
			$oSession = $this->queryGetSession( $oUser->user_login, $this->getSessionId() );
		}
		return $oSession;
	}

	/**
	 * Checks for and gets a user session.
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return SessionVO|null
	 */
	protected function queryGetSession( $sUsername, $sSessionId ) {
		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_retrieve.php' );

		$oRetrieve = new ICWP_WPSF_Query_Sessions_Retrieve();
		return $oRetrieve->setTable( $this->getTableName() )
						 ->retrieveUserSession( $sUsername, $sSessionId );
	}

	/**
	 * @param string $sWpUsername
	 * @return SessionVO[]
	 */
	public function queryGetActiveSessionsForUsername( $sWpUsername ) {
		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_retrieve.php' );

		$oRetrieve = new ICWP_WPSF_Query_Sessions_Retrieve();
		return $oRetrieve->setTable( $this->getTableName() )
						 ->retrieveForUsername( $sWpUsername );
	}

	/**
	 * @param string $sUsername
	 * @param string $sSessionId
	 * @return null|SessionVO
	 */
	protected function queryCreateSession( $sUsername, $sSessionId ) {
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
	 * @param SessionVO $oSession
	 * @return bool|int
	 */
	protected function queryTerminateSession( $oSession ) {
		if ( empty( $oSession ) ) {
			return true;
		}
		$this->doStatIncrement( 'user.session.terminate' );

		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_terminate.php' );
		$oTerminate = new ICWP_WPSF_Query_Sessions_Terminate();
		return $oTerminate->setTable( $this->getTableName() )
						  ->forUserSession( $oSession );
	}

	/**
	 * @return boolean
	 */
	protected function queryUpdateSessionLastActivity() {
		$oSession = $this->getCurrentSession();
		if ( empty( $oSession ) ) {
			return false;
		}
		require_once( dirname( dirname( __FILE__ ) ).'/query/sessions_update.php' );
		$oUpdate = new ICWP_WPSF_Query_Sessions_Update();
		return $oUpdate->setTable( $this->getTableName() )
					   ->update( $oSession );
	}

	/**
	 * @return array
	 */
	protected function getTableColumnsByDefinition() {
		$aDef = $this->getFeature()->getDef( 'sessions_table_columns' );
		return ( is_array( $aDef ) ? $aDef : array() );
	}
}