<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_UserManagement extends Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', [ $this, 'addUserStatusLastLogin' ] );
		add_filter( 'wpmu_users_columns', [ $this, 'addUserStatusLastLogin' ] );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $oMod->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/
		if ( $oMod->isUserSessionsManagementEnabled() ) {
			$this->getSubPro( 'sessions' )->execute();
		}

		if ( $oOpts->isPasswordPoliciesEnabled() ) {
			$this->getSubPro( 'passwords' )->execute();
		}

		if ( $oOpts->isSuspendEnabled() ) {
			$this->getSubPro( 'suspend' )->execute();
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

	/**
	 * @param \WP_User $oUser - not checking that user is valid
	 * @return $this
	 */
	private function sendLoginNotifications( $oUser ) {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		$aAdminEmails = $oMod->getAdminLoginNotificationEmails();
		$bAdmin = count( $aAdminEmails ) > 0;
		$bUser = $oMod->isSendUserEmailLoginNotification();

		// do some magic logic so we don't send both to the same person (the assumption being that the admin
		// email recipient is actually an admin (or they'll maybe not get any).
		if ( $bAdmin && $bUser && in_array( strtolower( $oUser->user_email ), $aAdminEmails ) ) {
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
		$oMeta->last_login_at = Services::Request()->ts();
		return $this;
	}

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array $aColumns
	 * @return array
	 */
	public function addUserStatusLastLogin( $aColumns ) {

		$sCustomColumnName = $this->getCon()->prefix( 'col_user_status' );
		if ( !isset( $aColumns[ $sCustomColumnName ] ) ) {
			$aColumns[ $sCustomColumnName ] = __( 'User Status', 'wp-simple-firewall' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $sContent, $sColumnName, $nUserId ) use ( $sCustomColumnName ) {

				if ( $sColumnName == $sCustomColumnName ) {
					$sValue = __( 'Not Recorded', 'wp-simple-firewall' );
					$oUser = Services::WpUsers()->getUserById( $nUserId );
					if ( $oUser instanceof \WP_User ) {
						$nLastLoginTime = $this->getCon()->getUserMeta( $oUser )->last_login_at;
						if ( $nLastLoginTime > 0 ) {
							$sValue = Services::Request()
											  ->carbon()
											  ->setTimestamp( $nLastLoginTime )
											  ->diffForHumans();
						}
					}
					$sNewContent = sprintf( '%s: %s', __( 'Last Login', 'wp-simple-firewall' ), $sValue );
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
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();

		$aUserCapToRolesMap = [
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		];

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

		$aMessage = [
			sprintf( __( 'As requested, %s is notifying you of a successful %s login to a WordPress site that you manage.', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName(),
				$sHumanName
			),
			'',
			sprintf( __( 'Important: %s', 'wp-simple-firewall' ), __( 'This user may now be subject to additional Two-Factor Authentication before completing their login.', 'wp-simple-firewall' ) ),
			'',
			__( 'Details for this user are below:', 'wp-simple-firewall' ),
			'- '.sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $sHomeUrl ),
			'- '.sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			'- '.sprintf( '%s: %s', __( 'Email', 'wp-simple-firewall' ), $oUser->user_email ),
			'- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
			'',
			__( 'Thanks.', 'wp-simple-firewall' )
		];

		$oEmailer = $this->getMod()
						 ->getEmailProcessor();
		foreach ( $oMod->getAdminLoginNotificationEmails() as $sEmail ) {
			$oEmailer->sendEmailWithWrap(
				$sEmail,
				sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), sprintf( __( '%s Just Logged Into %s', 'wp-simple-firewall' ), $sHumanName, $sHomeUrl ) ),
				$aMessage
			);
		}

		return true;
	}

	/**
	 * @param \WP_User $oUser
	 * @return bool
	 */
	private function sendUserLoginEmailNotification( $oUser ) {
		$oWp = Services::WpGeneral();
		$aMessage = [
			sprintf( __( '%s is notifying you of a successful login to your WordPress account.', 'wp-simple-firewall' ), $this->getCon()
																															  ->getHumanName() ),
			'',
			__( 'Details for this login are below:', 'wp-simple-firewall' ),
			'- '.sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $oWp->getHomeUrl() ),
			'- '.sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $oUser->user_login ),
			'- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
			'- '.sprintf( '%s: %s', __( 'Time', 'wp-simple-firewall' ), $oWp->getTimeStampForDisplay() ),
			'',
			__( 'If this is unexpected or suspicious, please contact your site administrator immediately.', 'wp-simple-firewall' ),
			'',
			__( 'Thanks.', 'wp-simple-firewall' )
		];

		return $this
			->getMod()
			->getEmailProcessor()
			->sendEmailWithWrap(
				$oUser->user_email,
				sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), __( 'A login to your WordPress account just occurred', 'wp-simple-firewall' ) ),
				$aMessage
			);
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