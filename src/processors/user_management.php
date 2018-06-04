<?php

if ( class_exists( 'ICWP_WPSF_Processor_UserManagement', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_UserManagement extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	protected $oProcessorSessions;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getFeature();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );

		add_action( 'init', array( $this, 'onWpInit' ) );
		add_action( 'wp_login', array( $this, 'onWpLogin' ) );

		if ( $oFO->isPasswordPoliciesEnabled() ) {
			$this->getProcessorPasswords()->run();
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( $this->loadWp()->getIsXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/
		if ( $oFO->getIsUserSessionsManagementEnabled() ) {
			$this->getProcessorSessions()->run();
		}
	}

	/**
	 */
	public function onWpInit() {
		$oWpUsers = $this->loadWpUsers();
		if ( $oWpUsers->isUserLoggedIn() ) {
			$oUser = $oWpUsers->getCurrentWpUser();
			$this->setPasswordStartedAt( $oUser ); // used by Password Policies
		}
	}

	/**
	 * Hooked to action wp_login
	 * @param $sUsername
	 */
	public function onWpLogin( $sUsername ) {
		$oUser = $this->loadWpUsers()->getUserByUsername( $sUsername );
		if ( $oUser instanceof WP_User ) {

			$this->setPasswordStartedAt( $oUser ); // used by Password Policies

			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeature();
			if ( $oFO->isSendEmailLoginNotification() ) {
				$this->sendLoginEmailNotification( $oUser );
			}
			$this->setUserLastLoginTime( $oUser );
		}
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	private function setPasswordStartedAt( $oUser ) {
		$oMeta = $this->getFeature()->getUserMeta( $oUser );

		$sCurrentPassHash = substr( sha1( $oUser->user_pass ), 6, 4 );
		if ( !isset( $oMeta->pass_hash ) || ( $oMeta->pass_hash != $sCurrentPassHash ) ) {
			$oMeta->pass_hash = $sCurrentPassHash;
			$oMeta->pass_started_at = $this->time();
		}
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	protected function setUserLastLoginTime( $oUser ) {
		$oMeta = $this->getFeature()->getUserMeta( $oUser );
		$oMeta->last_login_at = $this->time();
		return $this;
	}

	/**
	 * Adds the column to the users listing table to indicate whether WordPress will automatically update the plugins
	 * @param array $aColumns
	 * @return array
	 */
	public function fAddUserListLastLoginColumn( $aColumns ) {

		$sLastLoginColumnName = $this->prefix( 'last_login_at' );
		if ( !isset( $aColumns[ $sLastLoginColumnName ] ) ) {
			$aColumns[ $sLastLoginColumnName ] = _wpsf__( 'Last Login' );
			add_filter( 'manage_users_custom_column', array( $this, 'aPrintUsersListLastLoginColumnContent' ), 10, 3 );
		}
		return $aColumns;
	}

	/**
	 * Adds the column to the users listing table to stating last login time.
	 * @param string $sContent
	 * @param string $sColumnName
	 * @param int    $nUserId
	 * @return string
	 */
	public function aPrintUsersListLastLoginColumnContent( $sContent, $sColumnName, $nUserId ) {

		if ( $sColumnName != $this->prefix( 'last_login_at' ) ) {
			return $sContent;
		}

		$oWp = $this->loadWp();
		$nLastLoginTime = $this->loadWpUsers()->metaVoForUser( $this->prefix(), $nUserId )->last_login_at;

		$sLastLoginText = _wpsf__( 'Not Recorded' );
		if ( is_numeric( $nLastLoginTime ) && $nLastLoginTime > 0 ) {
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

		$sRoleToCheck = strtolower( apply_filters( $this->getFeature()
														->prefix( 'login-notification-email-role' ), 'administrator' ) );
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
			'- '.sprintf( _wpsf__( 'IP Address: %s' ), $this->ip() ),
			'',
			_wpsf__( 'Thanks.' )
		);

		return $this
			->getFeature()
			->getEmailProcessor()
			->sendEmailWithWrap(
				$this->getOption( 'enable_admin_login_email_notification' ),
				sprintf( _wpsf__( 'Notice - %s' ), sprintf( _wpsf__( '%s Just Logged Into %s' ), $sHumanName, $sHomeUrl ) ),
				$aMessage
			);
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Passwords
	 */
	protected function getProcessorPasswords() {
		$oProc = $this->getSubProcessor( 'passwords' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).'/usermanagement_passwords.php' );
			$oProc = new ICWP_WPSF_Processor_UserManagement_Passwords( $this->getFeature() );
			$this->aSubProcessors[ 'passwords' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	public function getProcessorSessions() {
		if ( !isset( $this->oProcessorSessions ) ) {
			require_once( dirname( __FILE__ ).'/usermanagement_sessions.php' );
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getFeature();
			$this->oProcessorSessions = new ICWP_WPSF_Processor_UserManagement_Sessions( $oFO );
		}
		return $this->oProcessorSessions;
	}

	/**
	 * @return string
	 */
	protected function getUserLastLoginKey() {
		return $this->getController()->prefixOption( 'last_login_at' );
	}
}