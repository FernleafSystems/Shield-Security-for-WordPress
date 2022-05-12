<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Users\BulkUpdateUserMeta;
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
		if ( $this->getCon()->this_req->wp_is_xmlrpc && $mod->isXmlrpcBypass() ) {
			return;
		}

		/** Everything from this point on must consider XMLRPC compatibility **/

		add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );

		// This controller handles visitor whitelisted status internally.
		$mod->getUserSuspendController()->execute();

		// All newly created users have their first seen and password start date set
		add_action( 'user_register', function ( $userID ) {
			$this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $userID ) );
		} );

		if ( !$this->getCon()->this_req->request_bypasses_all_restrictions ) {
			( new Lib\Session\UserSessionHandler() )
				->setMod( $this->getMod() )
				->execute();
			( new Lib\Password\UserPasswordHandler() )
				->setMod( $this->getMod() )
				->execute();
			( new Lib\Registration\EmailValidate() )
				->setMod( $this->getMod() )
				->execute();
		}
	}

	public function addAdminBarMenuGroup( array $groups ) :array {
		$con = $this->getCon();
		$WPUsers = Services::WpUsers();

		$recent = ( new FindSessions() )
			->setMod( $this->getMod() )
			->mostRecent();

		$thisGroup = [
			'title' => __( 'Recent Users', 'wp-simple-firewall' ),
			'href'  => $con->getModule_Insights()->getUrl_Sessions(),
			'items' => [],
		];
		if ( !empty( $recent ) ) {

			foreach ( $recent as $userID => $user ) {
				$thisGroup[ 'items' ][] = [
					'id'    => $con->prefix( 'meta-'.$userID ),
					'title' => sprintf( '<a href="%s">%s (%s)</a>',
						$WPUsers->getAdminUrl_ProfileEdit( $userID ),
						$user[ 'user_login' ],
						$user[ 'ip' ]
					),
				];
			}
		}

		if ( !empty( $thisGroup[ 'items' ] ) ) {
			$groups[] = $thisGroup;
		}
		return $groups;
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user = null ) {
		if ( !$user instanceof \WP_User && !empty( $username ) ) {
			$user = Services::WpUsers()->getUserByUsername( $username );
		}

		if ( $user instanceof \WP_User ) { // One might think it should be. It's not always the case it seems...
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
	 * Adds the column to the users listing table to indicate
	 * @param array $cols
	 * @return array
	 */
	public function addUserStatusLastLogin( $cols ) {

		$customColName = $this->getCon()->prefix( 'col_user_status' );
		if ( !isset( $cols[ $customColName ] ) ) {
			$cols[ $customColName ] = __( 'User Status', 'wp-simple-firewall' );
		}

		add_filter( 'manage_users_custom_column', function ( $content, $colName, $userID ) use ( $customColName ) {

			if ( $colName === $customColName ) {
				$user = Services::WpUsers()->getUserById( $userID );
				if ( $user instanceof \WP_User ) {

					$lastLoginAt = $this->getCon()->getUserMeta( $user )->record->last_login_at;
					if ( $lastLoginAt > 0 ) {
						$lastLogin = Services::Request()
											 ->carbon()
											 ->setTimestamp( $lastLoginAt )
											 ->diffForHumans();
					}
					else {
						$lastLogin = __( 'Not Recorded', 'wp-simple-firewall' );
					}

					$additionalContent = apply_filters( 'shield/user_status_column', [
						$content,
						sprintf( '<em>%s</em>: %s', __( 'Last Login', 'wp-simple-firewall' ), $lastLogin )
					], $user );

					$content = implode( '<br/>', array_filter( array_map( 'trim', $additionalContent ) ) );
				}
			}

			return $content;
		}, 10, 3 );

		return $cols;
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

		$roleToCheck = strtolower( apply_filters(
			$con->prefix( 'login-notification-email-role' ), 'administrator' ) );
		if ( !array_key_exists( $roleToCheck, $aUserCapToRolesMap ) ) {
			$roleToCheck = 'administrator';
		}
		$sHumanName = ucwords( str_replace( '_', ' ', $roleToCheck ) ).'+';

		$isUserSignificantEnough = false;
		foreach ( $aUserCapToRolesMap as $sRole => $sCap ) {
			if ( isset( $user->allcaps[ $sCap ] ) && $user->allcaps[ $sCap ] ) {
				$isUserSignificantEnough = true;
			}
			if ( $roleToCheck == $sRole ) {
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

	public function runHourlyCron() {
		( new BulkUpdateUserMeta() )
			->setCon( $this->getCon() )
			->execute();
	}
}