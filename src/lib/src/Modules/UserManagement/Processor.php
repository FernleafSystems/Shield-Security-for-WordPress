<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * This module is set to "run if whitelisted", so we must ensure any
	 * actions taken by this module respect whether the current visitor is whitelisted.
	 */
	public function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		// Adds last login indicator column
		add_filter( 'manage_users_columns', [ $this, 'addUserStatusLastLogin' ] );
		add_filter( 'wpmu_users_columns', [ $this, 'addUserStatusLastLogin' ] );

		/** Everything from this point on must consider XMLRPC compatibility **/

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $mod->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		// This controller handles visitor whitelisted status internally.
		( new Lib\Suspend\UserSuspendController() )
			->setMod( $this->getMod() )
			->execute();

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $nUserId ) {
			$this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );
		} );

		if ( !$mod->isVisitorWhitelisted() ) {

			( new Lib\Session\UserSessionHandler() )
				->setMod( $this->getMod() )
				->execute();

			( new Lib\Password\UserPasswordHandler() )
				->setMod( $this->getMod() )
				->execute();

			( new Lib\Registration\EmailValidate() )
				->setMod( $this->getMod() )
				->run();
		}
	}

	public function onWpInit() {
		$WPU = Services::WpUsers();
		if ( $WPU->isUserLoggedIn() ) {
			$this->setPasswordStartedAt( $WPU->getCurrentWpUser() ); // used by Password Policies
		}
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user = null ) {
		if ( !$user instanceof \WP_User ) {
			$user = Services::WpUsers()->getUserByUsername( $username );
		}
		$this->setPasswordStartedAt( $user )// used by Password Policies
			 ->setUserLastLoginTime( $user )
			 ->sendLoginNotifications( $user );
	}

	/**
	 * @param \WP_User $user - not checking that user is valid
	 * @return $this
	 */
	private function sendLoginNotifications( \WP_User $user ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$aAdminEmails = $mod->getAdminLoginNotificationEmails();
		$bAdmin = count( $aAdminEmails ) > 0;
		$bUser = $mod->isSendUserEmailLoginNotification();

		// do some magic logic so we don't send both to the same person (the assumption being that the admin
		// email recipient is actually an admin (or they'll maybe not get any).
		if ( $bAdmin && $bUser && in_array( strtolower( $user->user_email ), $aAdminEmails ) ) {
			$bUser = false;
		}

		if ( $bAdmin ) {
			$this->sendAdminLoginEmailNotification( $user );
		}
		if ( $bUser && !$this->isUserSubjectToLoginIntent( $user ) ) {
			$this->sendUserLoginEmailNotification( $user );
		}
		return $this;
	}

	private function setPasswordStartedAt( \WP_User $user ) :self {
		$this->getCon()
			 ->getUserMeta( $user )
			 ->setPasswordStartedAt( $user->user_pass );
		return $this;
	}

	protected function setUserLastLoginTime( \WP_User $user ) :self {
		$meta = $this->getCon()->getUserMeta( $user );
		$meta->last_login_at = Services::Request()->ts();
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$con = $this->getCon();

		$aUserCapToRolesMap = [
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		];

		$sRoleToCheck = strtolower( apply_filters(
			$con->prefix( 'login-notification-email-role' ), 'administrator' ) );
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
				$con->getHumanName(),
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
		foreach ( $mod->getAdminLoginNotificationEmails() as $sEmail ) {
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

	public function runDailyCron() {
		( new Lib\CleanExpired() )
			->setMod( $this->getMod() )
			->run();
	}
}