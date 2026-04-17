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
		$adminAlertData = self::con()->opts->optIs( 'instant_alert_admin_login', 'email' )
			? ( new AdminLoginAlertContextBuilder() )->build( $user )
			: null;
		$sendUser = self::con()->opts->optIs( 'enable_user_login_email_notification', 'Y' );

		if ( $adminAlertData !== null
			 && $sendUser
			 && \strtolower( $user->user_email ) === \strtolower( self::con()->comps->opts_lookup->getReportEmail() ) ) {
			$sendUser = false;
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
