<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\UserLoginNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionHandler {

	use ExecOnce;
	use PluginControllerConsumer;
	use WpLoginCapture;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions;
	}

	protected function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100 );
		add_filter( 'login_message', [ $this, 'printLinkToAdmin' ] );
	}

	protected function captureLogin( \WP_User $user ) {
		self::con()->user_metas->for( $user )->record->last_login_at = Services::Request()->ts();
		$this->sendLoginNotifications( $user );
	}

	/**
	 * Only show Go To Admin link for Authors+
	 * @param string|mixed $msg
	 */
	public function printLinkToAdmin( $msg = '' ) :string {
		$user = Services::WpUsers()->getCurrentWpUser();

		if ( \in_array( Services::Request()->query( 'action' ), [ '', 'login' ] )
			 && $user instanceof \WP_User
			 && self::con()->comps->session->current()->valid
		) {
			$msg .= sprintf( '<p class="message">%s %s<br />%s</p>',
				__( "You're already logged-in.", 'wp-simple-firewall' ),
				sprintf( '<span style="white-space: nowrap">(%s)</span>', $user->user_login ),
				( $user->user_level >= 2 ) ? sprintf( '<a href="%s">%s</a>',
					Services::WpGeneral()->getAdminUrl(),
					__( "Go To Admin", 'wp-simple-firewall' ).' &rarr;' ) : '' );
		}

		return \is_string( $msg ) ? $msg : '';
	}

	private function sendLoginNotifications( \WP_User $user ) {
		$adminEmails = $this->getAdminLoginNotificationEmails();
		$sendAdmin = \count( $adminEmails ) > 0;
		$sendUser = self::con()->opts->optIs( 'enable_user_login_email_notification', 'Y' );

		// do some magic logic so we don't send both to the same person (the assumption being that the admin
		// email recipient is actually an admin (or they'll maybe not get any).
		if ( $sendAdmin && $sendUser && \in_array( \strtolower( $user->user_email ), $adminEmails ) ) {
			$sendUser = false;
		}

		if ( $sendAdmin ) {
			$this->sendAdminLoginEmailNotification( $user );
		}
		if ( $sendUser ) {
			if ( !self::con()->comps->mfa->isSubjectToLoginIntent( $user ) ) {
				self::con()->email_con->sendVO(
					EmailVO::Factory(
						$user->user_email,
						sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), __( 'A login to your WordPress account just occurred', 'wp-simple-firewall' ) ),
						self::con()->action_router->render( UserLoginNotice::SLUG, [
							'home_url'  => Services::WpGeneral()->getHomeUrl(),
							'username'  => $user->user_login,
							'ip'        => self::con()->this_req->ip,
							'timestamp' => Services::WpGeneral()->getTimeStampForDisplay(),
						] )
					)
				);
			}
		}
	}

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string[]
	 */
	private function getAdminLoginNotificationEmails() :array {
		$con = self::con();
		$emails = [];

		$rawEmails = $con->opts->optGet( 'enable_admin_login_email_notification' );
		if ( !empty( $rawEmails ) ) {
			$emails = \array_values( \array_unique( \array_filter(
				\array_map(
					function ( $email ) {
						return \trim( \strtolower( $email ) );
					},
					\explode( ',', $rawEmails )
				),
				function ( $email ) {
					return Services::Data()->validEmail( $email );
				}
			) ) );

			if ( \count( $emails ) > 1 && !$con->isPremiumActive() ) {
				$emails = \array_slice( $emails, 0, 1 );
			}

			$con->opts->optSet( 'enable_admin_login_email_notification', \implode( ', ', $emails ) );
		}

		return $emails;
	}

	private function sendAdminLoginEmailNotification( \WP_User $user ) {
		$con = self::con();

		$userCapToRolesMap = [
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		];

		$roleToCheck = \strtolower( apply_filters( $con->prefix( 'login-notification-email-role' ), 'administrator' ) );
		if ( !\array_key_exists( $roleToCheck, $userCapToRolesMap ) ) {
			$roleToCheck = 'administrator';
		}
		$roleName = \ucwords( \str_replace( '_', ' ', $roleToCheck ) ).'+';

		$isUserSignificantEnough = false;
		foreach ( $userCapToRolesMap as $role => $cap ) {
			if ( isset( $user->allcaps[ $cap ] ) && $user->allcaps[ $cap ] ) {
				$isUserSignificantEnough = true;
			}
			if ( $roleToCheck == $role ) {
				break; // we've hit our role limit.
			}
		}
		if ( $isUserSignificantEnough ) {
			$homeURL = Services::WpGeneral()->getHomeUrl();
			foreach ( $this->getAdminLoginNotificationEmails() as $to ) {
				$con->email_con->sendEmailWithWrap(
					$to,
					sprintf( '%s - %s', __( 'Notice', 'wp-simple-firewall' ), sprintf( __( '%s Just Logged Into %s', 'wp-simple-firewall' ), $roleName, $homeURL ) ),
					[
						sprintf( __( 'As requested, %s is notifying you of a successful %s login to a WordPress site that you manage.', 'wp-simple-firewall' ), $con->labels->Name, $roleName ),
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

	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	private function checkCurrentSession() {
		$con = self::con();
		$srvIP = Services::IP();

		try {
			if ( !empty( $srvIP->isValidIp( $con->this_req->ip ) ) && !$srvIP->isLoopback() ) {
				$this->assessSession();
			}
		}
		catch ( \Exception $e ) {
			if ( !$srvIP->isLoopback() ) {
				$event = $e->getMessage();

				$con->comps->events->fireEvent( $event, [
					'audit_params' => [
						'user_login' => Services::WpUsers()->getCurrentWpUser()->user_login
					]
				] );

				$con->comps->session->terminateCurrentSession();
				$WPU = Services::WpUsers();
				is_admin() ? $WPU->forceUserRelogin( [ 'shield-forcelogout' => $event ] ) : $WPU->logoutUser( true );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function assessSession() {
		$sess = self::con()->comps->session->current();
		if ( !$sess->valid ) {
			throw new \Exception( 'session_notfound' );
		}

		$max = self::con()->comps->opts_lookup->getSessionMax();
		if ( $max > 0 && ( Services::Request()->ts() - $sess->login > $max ) ) {
			throw new \Exception( 'session_expired' );
		}
	}

	/**
	 * @param int|mixed $timeout
	 * @return int|mixed
	 */
	public function setMaxAuthCookieExpiration( $timeout ) {
		$max = self::con()->comps->opts_lookup->getSessionMax();
		return $max > 0 ? \min( $timeout, $max ) : $timeout;
	}

	/**
	 * @param \WP_Error|mixed $error
	 * @return \WP_Error|mixed
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
					$msg = sprintf( __( 'You do not currently have a %s user session.', 'wp-simple-firewall' ), self::con()->labels->Name );
					break;
				default:
					$msg = __( 'Your session was terminated.', 'wp-simple-firewall' );
					break;
			}
			$error->add( 'shield-forcelogout', $msg.'<br />'.__( 'Please login again.', 'wp-simple-firewall' ) );
		}
		return $error;
	}
}