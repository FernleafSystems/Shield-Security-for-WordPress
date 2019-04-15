<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_UserManagement extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', array( $this, 'addUserStatusLastLogin' ) );
		add_filter( 'wpmu_users_columns', array( $this, 'addUserStatusLastLogin' ) );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $oFO->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/
		if ( $oFO->isUserSessionsManagementEnabled() ) {
			$this->getProcessorSessions()->run();
		}

		if ( $oFO->isPasswordPoliciesEnabled() ) {
			$this->getProcessorPasswords()->run();
		}

		if ( $oFO->isSuspendEnabled() ) {
			$this->getProcessorSuspend()->run();
		}

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $nUserId ) {
			$this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );
		} );
	}

	/**
	 */
	public function onWpInit() {
		parent::onWpInit();

		$oWpUsers = Services::WpUsers();
		if ( $oWpUsers->isUserLoggedIn() ) {
			$this->setPasswordStartedAt( $oWpUsers->getCurrentWpUser() ); // used by Password Policies
		}
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser = null ) {
		if ( !$oUser instanceof \WP_User ) {
			$oUser = Services::WpUsers()->getUserByUsername( $sUsername );
		}
		$this->setPasswordStartedAt( $oUser )// used by Password Policies
			 ->setUserLastLoginTime( $oUser )
			 ->sendLoginNotifications( $oUser );
	}

	public function runDailyCron() {
	}

	/**
	 * @param \WP_User $oUser - not checking that user is valid
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
	 * @param \WP_User $oUser
	 * @return $this
	 */
	private function setPasswordStartedAt( $oUser ) {
		$this->getCon()
			 ->getUserMeta( $oUser )
			 ->setPasswordStartedAt( $oUser->user_pass );
		return $this;
	}

	/**
	 * @param \WP_User $oUser
	 * @return $this
	 */
	protected function setUserLastLoginTime( $oUser ) {
		$oMeta = $this->getCon()->getUserMeta( $oUser );
		$oMeta->last_login_at = $this->time();
		return $this;
	}

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array $aColumns
	 * @return array
	 */
	public function addUserStatusLastLogin( $aColumns ) {

		$sCustomColumnName = $this->prefix( 'col_user_status' );
		if ( !isset( $aColumns[ $sCustomColumnName ] ) ) {
			$aColumns[ $sCustomColumnName ] = _wpsf__( 'User Status' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $sContent, $sColumnName, $nUserId ) use ( $sCustomColumnName ) {

				if ( $sColumnName == $sCustomColumnName ) {
					$sValue = _wpsf__( 'Not Recorded' );
					$oUser = Services::WpUsers()->getUserById( $nUserId );
					if ( $oUser instanceof \WP_User ) {
						$nLastLoginTime = $this->getCon()->getUserMeta( $oUser )->last_login_at;
						if ( $nLastLoginTime > 0 ) {
							$sValue = ( new \Carbon\Carbon() )->setTimestamp( $nLastLoginTime )->diffForHumans();
						}
					}
					$sNewContent = sprintf( '%s: %s', _wpsf__( 'Last Login' ), $sValue );
					$sContent = empty( $sContent ) ? $sNewContent : $sContent.'<br/>'.$sNewContent;
				}

				return $sContent;
			},
			10, 3
		);

		return $aColumns;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function sendAdminLoginEmailNotification( $oUser ) {
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

		$sHomeUrl = Services::WpGeneral()->getHomeUrl();

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
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function sendUserLoginEmailNotification( $oUser ) {
		$oWp = Services::WpGeneral();
		$aMessage = array(
			sprintf( _wpsf__( '%s is notifying you of a successful login to your WordPress account.' ), $this->getCon()
																											 ->getHumanName() ),
			'',
			_wpsf__( 'Details for this login are below:' ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Site URL' ), $oWp->getHomeUrl() ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Username' ), $oUser->user_login ),
			'- '.sprintf( '%s: %s', _wpsf__( 'IP Address' ), $this->ip() ),
			'- '.sprintf( '%s: %s', _wpsf__( 'Time' ), $oWp->getTimeStampForDisplay() ),
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
	 * @return ICWP_WPSF_Processor_UserManagement_Passwords|mixed
	 */
	protected function getProcessorPasswords() {
		return $this->getSubPro( 'passwords' );
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Sessions|mixed
	 */
	public function getProcessorSessions() {
		return $this->getSubPro( 'sessions' );
	}

	/**
	 * @return ICWP_WPSF_Processor_UserManagement_Suspend|mixed
	 */
	protected function getProcessorSuspend() {
		return $this->getSubPro( 'suspend' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'passwords' => 'ICWP_WPSF_Processor_UserManagement_Passwords',
			'sessions'  => 'ICWP_WPSF_Processor_UserManagement_Sessions',
			'suspend'   => 'ICWP_WPSF_Processor_UserManagement_Suspend',
		];
	}
}