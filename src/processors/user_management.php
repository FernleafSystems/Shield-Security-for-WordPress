<?php

class ICWP_WPSF_Processor_UserManagement extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	protected $oProcessorSessions;

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'fAddUserListLastLoginColumn' ) );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( $this->loadWp()->isXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/
		if ( $oFO->isUserSessionsManagementEnabled() ) {
			$this->getProcessorSessions()->run();
		}

		if ( $oFO->isPasswordPoliciesEnabled() ) {
			$this->getProcessorPasswords()->run();
		}
	}

	/**
	 */
	public function onWpInit() {
		parent::onWpInit();

		$oWpUsers = $this->loadWpUsers();
		if ( $oWpUsers->isUserLoggedIn() ) {
			$this->setPasswordStartedAt( $oWpUsers->getCurrentWpUser() ); // used by Password Policies
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
		$this->setPasswordStartedAt( $oUser )// used by Password Policies
			 ->setUserLastLoginTime( $oUser )
			 ->sendLoginNotifications( $oUser );
	}

	/**
	 * @param WP_User $oUser - not checking that user is valid
	 * @return $this
	 */
	private function sendLoginNotifications( $oUser ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		$bAdmin = $oFO->isSendAdminEmailLoginNotification();
		$bUser = $oFO->isSendUserEmailLoginNotification();

		// do some magic logic so we don't send both to the same person (the assumption being that the admin
		// email recipient is actually an admin (or they'll maybe not get any).
		if ( $bAdmin && $bUser && ( $oFO->getAdminLoginNotificationEmail() === $oUser->user_email ) ) {
			$bUser = false;
		}

		if ( $bAdmin ) {
			$this->sendAdminLoginEmailNotification( $oUser );
		}
		if ( $bUser && !$this->isUserSubjectToLoginIntent( $oUser ) ) {
			$this->sendUserLoginEmailNotification( $oUser );
		}
		return $this;
	}

	/**
	 * @param WP_User $oUser
	 * @return $this
	 */
	private function setPasswordStartedAt( $oUser ) {
		$oMeta = $this->getCon()->getUserMeta( $oUser );

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
		$oMeta = $this->getCon()->getUserMeta( $oUser );
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
	private function sendAdminLoginEmailNotification( $oUser ) {
		if ( !( $oUser instanceof WP_User ) ) {
			return false;
		}
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		$aUserCapToRolesMap = array(
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		);

		$sRoleToCheck = strtolower( apply_filters( $this->getMod()
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
				$this->getCon()->getHumanName(),
				$sHumanName
			),
			'',
			sprintf( _wpsf__( 'Important: %s' ), _wpsf__( 'This user may now be subject to additional Two-Factor Authentication before completing their login.' ) ),
			'',
			_wpsf__( 'Details for this user are below:' ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Site URL' ), $sHomeUrl ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Username' ), $oUser->user_login ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Email' ), $oUser->user_email ),
			'- '.sprintf( '%s: %s', _wpsf__( 'IP Address' ), $this->ip() ),
			'',
			_wpsf__( 'Thanks.' )
		);

		return $this
			->getMod()
			->getEmailProcessor()
			->sendEmailWithWrap(
				$oFO->getAdminLoginNotificationEmail(),
				sprintf( '%s - %s', _wpsf__( 'Notice' ), sprintf( _wpsf__( '%s Just Logged Into %s' ), $sHumanName, $sHomeUrl ) ),
				$aMessage
			);
	}

	/**
	 * @param WP_User $oUser
	 * @return bool
	 */
	private function sendUserLoginEmailNotification( $oUser ) {
		$aMessage = array(
			sprintf( _wpsf__( '%s is notifying you of a successful login to your WordPress account.' ), $this->getCon()
																											 ->getHumanName() ),
			'',
			_wpsf__( 'Details for this login are below:' ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Site URL' ), $this->loadWp()->getHomeUrl() ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Username' ), $oUser->user_login ),
			'- '.sprintf( '%s: %s', _wpsf__( 'IP Address' ), $this->ip() ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Time' ), $this->loadWp()->getTimeStampForDisplay() ),
			'',
			_wpsf__( 'If this is unexpected or suspicious, please contact your site administrator immediately.' ),
			'',
			_wpsf__( 'Thanks.' )
		);

		return $this
			->getMod()
			->getEmailProcessor()
			->sendEmailWithWrap(
				$oUser->user_email,
				sprintf( '%s - %s', _wpsf__( 'Notice' ), _wpsf__( 'A login to your WordPress account just occurred' ) ),
				$aMessage
			);
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Passwords
	 */
	protected function getProcessorPasswords() {
		$oProc = $this->getSubPro( 'passwords' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/usermanagement_passwords.php' );
			$oProc = new ICWP_WPSF_Processor_UserManagement_Passwords( $this->getMod() );
			$this->aSubPros[ 'passwords' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Sessions
	 */
	public function getProcessorSessions() {
		if ( !isset( $this->oProcessorSessions ) ) {
			require_once( __DIR__.'/usermanagement_sessions.php' );
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getMod();
			$this->oProcessorSessions = new ICWP_WPSF_Processor_UserManagement_Sessions( $oFO );
		}
		return $this->oProcessorSessions;
	}

	/**
	 * @return string
	 */
	protected function getUserLastLoginKey() {
		return $this->getCon()->prefixOption( 'last_login_at' );
	}
}