<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\UserLoginNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionHandler extends ExecOnceModConsumer {

	use WpLoginCapture;

	protected function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100 );
		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
	}

	protected function captureLogin( \WP_User $user ) {
		$this->getCon()
			 ->getUserMeta( $user )
			->record
			->last_login_at = Services::Request()->ts();
		$this->sendLoginNotifications( $user );
	}

	/**
	 * Only show Go To Admin link for Authors+
	 * @param string $msg
	 */
	public function printLinkToAdmin( $msg = '' ) :string {
		$user = Services::WpUsers()->getCurrentWpUser();

		if ( in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && $user instanceof \WP_User
			 && $this->getCon()->getModule_Plugin()->getSessionCon()->current()->valid
		) {
			$msg .= sprintf( '<p class="message">%s %s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' ),
				sprintf( '<span style="white-space: nowrap">(%s)</span>', $user->user_login ),
				( $user->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}

		return is_string( $msg ) ? $msg : '';
	}

	private function sendLoginNotifications( \WP_User $user ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$adminEmails = $this->getAdminLoginNotificationEmails();
		$sendAdmin = count( $adminEmails ) > 0;
		$sendUser = $opts->isOpt( 'enable_user_login_email_notification', 'Y' );

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
	}

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string[]
	 */
	public function getAdminLoginNotificationEmails() :array {
		$emails = [];

		$rawEmails = $this->getOptions()->getOpt( 'enable_admin_login_email_notification', '' );
		if ( !empty( $rawEmails ) ) {
			$emails = array_values( array_unique( array_filter(
				array_map(
					function ( $email ) {
						return trim( strtolower( $email ) );
					},
					explode( ',', $rawEmails )
				),
				function ( $email ) {
					return Services::Data()->validEmail( $email );
				}
			) ) );

			if ( count( $emails ) > 1 && !$this->getCon()->isPremiumActive() ) {
				$emails = array_slice( $emails, 0, 1 );
			}

			$this->getOptions()
				 ->setOpt( 'enable_admin_login_email_notification', implode( ', ', $emails ) );
		}

		return $emails;
	}

	private function sendAdminLoginEmailNotification( \WP_User $user ) {
		$con = $this->getCon();

		$userCapToRolesMap = [
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		];

		$roleToCheck = strtolower( apply_filters(
			$con->prefix( 'login-notification-email-role' ), 'administrator' ) );
		if ( !array_key_exists( $roleToCheck, $userCapToRolesMap ) ) {
			$roleToCheck = 'administrator';
		}
		$pluginName = ucwords( str_replace( '_', ' ', $roleToCheck ) ).'+';

		$isUserSignificantEnough = false;
		foreach ( $userCapToRolesMap as $sRole => $sCap ) {
			if ( isset( $user->allcaps[ $sCap ] ) && $user->allcaps[ $sCap ] ) {
				$isUserSignificantEnough = true;
			}
			if ( $roleToCheck == $sRole ) {
				break; // we've hit our role limit.
			}
		}
		if ( $isUserSignificantEnough ) {

			$homeURL = Services::WpGeneral()->getHomeUrl();

			$emailer = $this->getMod()
							->getEmailProcessor();
			foreach ( $this->getAdminLoginNotificationEmails() as $to ) {
				$emailer->sendEmailWithWrap(
					$to,
					sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), sprintf( __( '%s Just Logged Into %s', 'wp-simple-firewall' ), $pluginName, $homeURL ) ),
					[
						sprintf( __( 'As requested, %s is notifying you of a successful %s login to a WordPress site that you manage.', 'wp-simple-firewall' ),
							$con->getHumanName(),
							$pluginName
						),
						'',
						sprintf( __( 'Important: %s', 'wp-simple-firewall' ), __( 'This user may now be subject to additional Two-Factor Authentication before completing their login.', 'wp-simple-firewall' ) ),
						'',
						__( 'Details for this user are below:', 'wp-simple-firewall' ),
						'- '.sprintf( '%s: %s', __( 'Site URL', 'wp-simple-firewall' ), $homeURL ),
						'- '.sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
						'- '.sprintf( '%s: %s', __( 'Email', 'wp-simple-firewall' ), $user->user_email ),
						'- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $con->this_req->ip ),
						'',
						__( 'Thanks.', 'wp-simple-firewall' )
					]
				);
			}
		}
	}

	private function sendUserLoginEmailNotification( \WP_User $user ) {
		$con = $this->getCon();
		$this->getMod()
			 ->getEmailProcessor()
			 ->send(
				 $user->user_email,
				 sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), __( 'A login to your WordPress account just occurred', 'wp-simple-firewall' ) ),
				 $this->getCon()->action_router->render( UserLoginNotice::SLUG, [
					 'home_url'  => Services::WpGeneral()->getHomeUrl(),
					 'username'  => $user->user_login,
					 'ip'        => $con->this_req->ip,
					 'timestamp' => Services::WpGeneral()->getTimeStampForDisplay(),
				 ] )
			 );
	}

	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	private function checkCurrentSession() {
		$con = $this->getCon();
		$srvIP = Services::IP();

		try {
			if ( !empty( $srvIP->isValidIp( $con->this_req->ip ) ) && !$srvIP->isLoopback() ) {
				$this->assessSession();
			}
		}
		catch ( \Exception $e ) {
			if ( $e->getMessage() === 'session_iplock' ) {
				$srvIP->getServerPublicIPs( true );
			}
			if ( !$srvIP->isLoopback() ) {
				$event = $e->getMessage();

				$con->fireEvent( $event, [
					'audit_params' => [
						'user_login' => Services::WpUsers()->getCurrentWpUser()->user_login
					]
				] );

				$con->getModule_Plugin()
					->getSessionCon()
					->terminateCurrentSession();
				$WPU = Services::WpUsers();
				is_admin() ? $WPU->forceUserRelogin( [ 'shield-forcelogout' => $event ] ) : $WPU->logoutUser( true );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function assessSession() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$sess = $this->getCon()
					 ->getModule_Plugin()
					 ->getSessionCon()
					 ->current();
		if ( !$sess->valid ) {
			throw new \Exception( 'session_notfound' );
		}

		$ts = Services::Request()->ts();

		if ( $opts->hasMaxSessionTimeout() && ( $ts - $sess->login > $opts->getMaxSessionTime() ) ) {
			throw new \Exception( 'session_expired' );
		}

		if ( $opts->hasSessionIdleTimeout() && ( $ts - $sess->shield[ 'last_activity_at' ] > $opts->getIdleTimeoutInterval() ) ) {
			throw new \Exception( 'session_idle' );
		}

		$srvIP = Services::IP();
		if ( $opts->isLockToIp() && !$srvIP->IpIn( $this->getCon()->this_req->ip, [ $sess->ip ] ) ) {
			throw new \Exception( 'session_iplock' );
		}
	}

	/**
	 * @param int $timeout
	 * @return int
	 */
	public function setMaxAuthCookieExpiration( $timeout ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->hasMaxSessionTimeout() ? min( $timeout, $opts->getMaxSessionTime() ) : $timeout;
	}

	/**
	 * @param \WP_Error $error
	 * @return \WP_Error
	 */
	public function addLoginMessage( $error ) {
		if ( !$error instanceof \WP_Error ) {
			$error = new \WP_Error();
		}

		$forceLogoutParam = Services::Request()->query( 'shield-forcelogout' );
		if ( $forceLogoutParam ) {

			switch ( $forceLogoutParam ) {
				case 'session_expired':
					$msg = __( 'Your session has expired.', 'wp-simple-firewall' );
					break;

				case 'session_idle':
					$msg = __( 'Your session was idle for too long.', 'wp-simple-firewall' );
					break;

				case 'session_iplock':
					$msg = __( 'Your session was locked to another IP Address.', 'wp-simple-firewall' );
					break;

				case 'session_notfound':
					$msg = sprintf(
						__( 'You do not currently have a %s user session.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					);
					break;

				default:
					$msg = __( 'Your session was terminated.', 'wp-simple-firewall' );
					break;
			}

			$msg .= '<br />'.__( 'Please login again.', 'wp-simple-firewall' );
			$error->add( 'shield-forcelogout', $msg );
		}
		return $error;
	}
}