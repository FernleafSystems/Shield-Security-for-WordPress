<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * This module is set to "run if whitelisted", so we must ensure any
	 * actions taken by this module respect whether the current visitor is whitelisted.
	 */
	protected function run() {
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

		add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );

		// This controller handles visitor whitelisted status internally.
		( new Lib\Suspend\UserSuspendController() )
			->setMod( $this->getMod() )
			->execute();

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $userID ) {
			$this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $userID ) );
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

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user = null ) {
		if ( !$user instanceof \WP_User && !empty( $username ) ) {
			$user = Services::WpUsers()->getUserByUsername( $username );
		}
		// One might think it should be. It's not always the case it seems...
		if ( $user instanceof \WP_User ) {
			$meta = $this->getCon()->getUserMeta( $user );
			$meta->updatePasswordStartedAt( $user->user_pass );
			$meta->record->last_login_at = Services::Request()->ts();
			$this->sendLoginNotifications( $user );
		}
	}

	/**
	 * @return $this
	 */
	private function sendLoginNotifications( \WP_User $user ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$adminEmails = $mod->getAdminLoginNotificationEmails();
		$sendAdmin = count( $adminEmails ) > 0;
		$sendUser = $mod->isSendUserEmailLoginNotification();

		// do some magic logic so we don't send both to the same person (the assumption being that the admin
		// email recipient is actually an admin (or they'll maybe not get any).
		if ( $sendAdmin && $sendUser && in_array( strtolower( $user->user_email ), $adminEmails ) ) {
			$sendUser = false;
		}

		if ( $sendAdmin ) {
			$this->sendAdminLoginEmailNotification( $user );
		}
		if ( $sendUser ) {
			$hasLoginIntent = $this->getCon()
								   ->getModule_LoginGuard()
								   ->getMfaController()
								   ->isSubjectToLoginIntent( $user );
			if ( !$hasLoginIntent ) {
				$this->sendUserLoginEmailNotification( $user );
			}
		}
		return $this;
	}

	/**
	 * @deprecated 13.1
	 */
	private function setPasswordStartedAt( \WP_User $user ) :self {
		return $this;
	}

	/**
	 * @deprecated 13.1
	 */
	protected function setUserLastLoginTime( \WP_User $user ) :self {
		return $this;
	}

	/**
	 * Adds the column to the users listing table to indicate
	 * @param array $aColumns
	 * @return array
	 */
	public function addUserStatusLastLogin( $aColumns ) {

		$customColName = $this->getCon()->prefix( 'col_user_status' );
		if ( !isset( $aColumns[ $customColName ] ) ) {
			$aColumns[ $customColName ] = __( 'User Status', 'wp-simple-firewall' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $content, $colName, $userID ) use ( $customColName ) {

				if ( $colName == $customColName ) {
					$value = __( 'Not Recorded', 'wp-simple-firewall' );
					$user = Services::WpUsers()->getUserById( $userID );
					if ( $user instanceof \WP_User ) {
						$lastLogin = $this->getCon()->getUserMeta( $user )->record->last_login_at;
						if ( $lastLogin > 0 ) {
							$value = Services::Request()
											 ->carbon()
											 ->setTimestamp( $lastLogin )
											 ->diffForHumans();
						}
					}
					$newContent = sprintf( '%s: %s', __( 'Last Login', 'wp-simple-firewall' ), $value );
					$content = empty( $content ) ? $newContent : $content.'<br/>'.$newContent;
				}

				return $content;
			},
			10, 3
		);

		return $aColumns;
	}

	private function sendAdminLoginEmailNotification( \WP_User $user ) {
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

		$isUserSignificantEnough = false;
		foreach ( $aUserCapToRolesMap as $sRole => $sCap ) {
			if ( isset( $user->allcaps[ $sCap ] ) && $user->allcaps[ $sCap ] ) {
				$isUserSignificantEnough = true;
			}
			if ( $sRoleToCheck == $sRole ) {
				break; // we've hit our role limit.
			}
		}
		if ( $isUserSignificantEnough ) {

			$sHomeUrl = Services::WpGeneral()->getHomeUrl();

			$emailer = $this->getMod()
							->getEmailProcessor();
			foreach ( $mod->getAdminLoginNotificationEmails() as $to ) {
				$emailer->sendEmailWithWrap(
					$to,
					sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), sprintf( __( '%s Just Logged Into %s', 'wp-simple-firewall' ), $sHumanName, $sHomeUrl ) ),
					[
						sprintf( __( 'As requested, %s is notifying you of a successful %s login to a WordPress site that you manage.', 'wp-simple-firewall' ),
							$con->getHumanName(),
							$sHumanName
						),
						'',
						sprintf( __( 'Important: %s', 'wp-simple-firewall' ), __( 'This user may now be subject to additional Two-Factor Authentication before completing their login.', 'wp-simple-firewall' ) ),
						'',
						__( 'Details for this user are below:', 'wp-simple-firewall' ),
						'- '.sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $sHomeUrl ),
						'- '.sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
						'- '.sprintf( '%s: %s', __( 'Email', 'wp-simple-firewall' ), $user->user_email ),
						'- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()
																								  ->getRequestIp() ),
						'',
						__( 'Thanks.', 'wp-simple-firewall' )
					]
				);
			}
		}
	}

	private function sendUserLoginEmailNotification( \WP_User $user ) {
		$WP = Services::WpGeneral();
		$this->getMod()
			 ->getEmailProcessor()
			 ->sendEmailWithWrap(
				 $user->user_email,
				 sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), __( 'A login to your WordPress account just occurred', 'wp-simple-firewall' ) ),
				 [
					 sprintf( __( '%s is notifying you of a successful login to your WordPress account.', 'wp-simple-firewall' ), $this->getCon()
																																	   ->getHumanName() ),
					 '',
					 __( 'Details for this login are below:', 'wp-simple-firewall' ),
					 '- '.sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $WP->getHomeUrl() ),
					 '- '.sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
					 '- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
					 '- '.sprintf( '%s: %s', __( 'Time', 'wp-simple-firewall' ), $WP->getTimeStampForDisplay() ),
					 '',
					 __( 'If this is unexpected or suspicious, please contact your site administrator immediately.', 'wp-simple-firewall' ),
					 '',
					 __( 'Thanks.', 'wp-simple-firewall' )
				 ]
			 );
	}

	public function runDailyCron() {
		( new Lib\CleanExpired() )
			->setMod( $this->getMod() )
			->run();
	}
}