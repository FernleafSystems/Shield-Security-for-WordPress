<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_UserManagement extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	protected $oProcessorSessions;

	/**
	 * @return bool
	 */
	public function run() {

		// Adds last login indicator column to all plugins in plugin listing.
		add_filter( 'manage_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );

		// Various stuff.
		add_action( 'init', array( $this, 'onInit' ), 1 );

		// Handles login notification emails and setting last user login
		add_action( 'wp_login', array( $this, 'onWpLogin' ) );

		// XML-RPC Compatibility
		if ( $this->loadWp()->getIsXmlrpc() && $this->getIsOption( 'enable_xmlrpc_compatibility', 'Y' ) ) {
			return true;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->getIsUserSessionsManagementEnabled() ) {
			$this->getProcessorSessions()->run();
		}

		return true;
	}

	public function onInit() {
		add_filter( 'login_message', array( $this, 'printLinkToAdmin' ) );
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
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeature();
			if ( $oFO->getIsUserSessionsManagementEnabled() && $this->getProcessorSessions()
																	->getCurrentUserHasValidSession() ) {
				$sMessage = sprintf(
								'<p class="message">%s<br />%s</p>',
								_wpsf__( "It appears you're already logged-in." ).sprintf( ' <span style="white-space: nowrap">(%s)</span>', $oWpUsers->getCurrentWpUser()
																																					  ->get( 'user_login' ) ),
								( $oWpUsers->getCurrentUserLevel() >= 2 ) ? sprintf( '<a href="%s">%s</a>', $this->loadWp()
																												 ->getUrl_WpAdmin(), _wpsf__( "Go To Admin" ).' &rarr;' ) : ''
							).$sMessage;
			}
		}
		return $sMessage;
	}

	/**
	 * Hooked to action wp_login
	 * @param $sUsername
	 */
	public function onWpLogin( $sUsername ) {
		$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
		if ( $oUser instanceof WP_User ) {

			if ( $this->loadDataProcessor()
					  ->validEmail( $this->getOption( 'enable_admin_login_email_notification' ) ) ) {
				$this->sendLoginEmailNotification( $oUser );
			}
			$this->setUserLastLoginTime( $oUser );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function setUserLastLoginTime( $oUser ) {
		return $this->loadWpUsers()->updateUserMeta( $this->getUserLastLoginKey(), $this->time(), $oUser->ID );
	}

	/**
	 * Adds the column to the users listing table to indicate whether WordPress will automatically update the plugins
	 * @param array $aColumns
	 * @return array
	 */
	public function fAddUserListLastLoginColumn( $aColumns ) {

		$sLastLoginColumnName = $this->getUserLastLoginKey();
		if ( !isset( $aColumns[ $sLastLoginColumnName ] ) ) {
			$aColumns[ $sLastLoginColumnName ] = _wpsf__( 'Last Login' );
			add_filter( 'manage_users_custom_column', array( $this, 'aPrintUsersListLastLoginColumnContent' ), 10, 3 );
		}
		return $aColumns;
	}

	/**
	 * Adds the column to the users listing table to indicate whether WordPress will automatically update the plugins
	 * @param string $sContent
	 * @param string $sColumnName
	 * @param int    $nUserId
	 * @return string
	 */
	public function aPrintUsersListLastLoginColumnContent( $sContent, $sColumnName, $nUserId ) {
		$sLastLoginKey = $this->getUserLastLoginKey();
		if ( $sColumnName != $sLastLoginKey ) {
			return $sContent;
		}
		$oWp = $this->loadWp();
		$nLastLoginTime = $this->loadWpUsers()->getUserMeta( $sLastLoginKey, $nUserId );

		$sLastLoginText = _wpsf__( 'Not Recorded' );
		if ( !empty( $nLastLoginTime ) && is_numeric( $nLastLoginTime ) ) {
			$sLastLoginText = $oWp->getTimeStringForDisplay( $nLastLoginTime );
		}
		return $sLastLoginText;
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	protected function sendLoginEmailNotification( $oUser ) {
		if ( !( $oUser instanceof WP_User ) ) {
			return false;
		}

		$aUserCapToRolesMap = array(
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		);

		$sRoleToCheck = strtolower( apply_filters( $this->getFeature()->prefix( 'login-notification-email-role' ), 'administrator' ) );
		if ( !array_key_exists( $sRoleToCheck, $aUserCapToRolesMap ) ) {
			$sRoleToCheck = 'administrator';
		}
		$sHumanName = ucwords( str_replace( '_', ' ', $sRoleToCheck ) ).'+';

		$bIsUserSignificantEnough = false;
		foreach ( $aUserCapToRolesMap as $sRole => $sCap ) {
			if ( isset( $oUser->allcaps[ $sCap ] ) && $oUser->allcaps[ $sCap ] ) {
				$bIsUserSignificantEnough = true;
			}
			if ( $sRoleToCheck == $sRole ) {
				break; // we've hit our role limit.
			}
		}
		if ( !$bIsUserSignificantEnough ) {
			return false;
		}

		$sHomeUrl = $this->loadWp()->getHomeUrl();

		$aMessage = array(
			sprintf( _wpsf__( 'As requested, %s is notifying you of a successful %s login to a WordPress site that you manage.' ),
				$this->getController()->getHumanName(),
				$sHumanName
			),
			'',
			sprintf( _wpsf__( 'Important: %s' ), _wpsf__( 'This user may now be subject to additional Two-Factor Authentication before completing their login.' ) ),
			'',
			_wpsf__( 'Details for this user are below:' ),
			'- '.sprintf( _wpsf__( 'Site URL: %s' ), $sHomeUrl ),
			'- '.sprintf( _wpsf__( 'Username: %s' ), $oUser->get( 'user_login' ) ),
			'- '.sprintf( _wpsf__( 'User Email: %s' ), $oUser->get( 'user_email' ) ),
			'- '.sprintf( _wpsf__( 'IP Address: %s' ), $this->human_ip() ),
			'',
			_wpsf__( 'Thanks.' )
		);

		return $this
			->getFeature()
			->getEmailProcessor()
			->sendEmailTo(
				$this->getOption( 'enable_admin_login_email_notification' ),
				sprintf( _wpsf__( 'Notice - %s' ), sprintf( _wpsf__( '%s Just Logged Into %s' ), $sHumanName, $sHomeUrl ) ),
				$aMessage
			);
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	protected function getProcessorSessions() {
		if ( !isset( $this->oProcessorSessions ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'usermanagement_sessions.php' );
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeature();
			$this->oProcessorSessions = new ICWP_WPSF_Processor_UserManagement_Sessions( $oFO );
		}
		return $this->oProcessorSessions;
	}

	/**
	 * @param string $sWpUsername
	 * @return array|bool
	 */
	public function getActiveUserSessionRecords( $sWpUsername = '' ) {
		return $this->getProcessorSessions()->getActiveUserSessionRecords( $sWpUsername );
	}

	/**
	 * @param integer $nTime - number of seconds back from now to look
	 * @return array|boolean
	 */
	public function getPendingOrFailedUserSessionRecordsSince( $nTime = 0 ) {
		return $this->getProcessorSessions()->getPendingOrFailedUserSessionRecordsSince( $nTime );
	}

	/**
	 * @return string
	 */
	protected function getUserLastLoginKey() {
		return $this->getController()->doPluginOptionPrefix( 'userlastlogin' );
	}
}