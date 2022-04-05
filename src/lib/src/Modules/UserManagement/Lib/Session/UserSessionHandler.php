<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionHandler extends ExecOnceModConsumer {

	use WpLoginCapture;

	protected function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100 );
	}

	protected function captureLogin( \WP_User $user ) {
		$this->enforceSessionLimits( $user );
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
			if ( !empty( $srvIP->isValidIp( $srvIP->getRequestIp() ) ) && !$srvIP->isLoopback() ) {
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

				$con->getModule_Sessions()
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
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		$sess = $this->getCon()
					 ->getModule_Sessions()
					 ->getSessionCon()
					 ->getCurrentWP();
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
		if ( $opts->isLockToIp() && !$srvIP->checkIp( $srvIP->getRequestIp(), $sess->ip ) ) {
			throw new \Exception( 'session_iplock' );
		}
	}

	/**
	 * @param int $timeout
	 * @return int
	 */
	public function setMaxAuthCookieExpiration( $timeout ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		return $opts->hasMaxSessionTimeout() ? min( $timeout, $opts->getMaxSessionTime() ) : $timeout;
	}

	private function enforceSessionLimits( \WP_User $user ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		$sessionLimit = (int)$opts->getOpt( 'session_username_concurrent_limit', 1 );
		if ( $sessionLimit > 0 ) {
			try {
				$this->getCon()
					 ->getModule_Sessions()
					 ->getDbHandler_Sessions()
					 ->getQueryDeleter()
					 ->addWhere( 'wp_username', $user->user_login )
					 ->deleteExcess( $sessionLimit, 'last_activity_at', true );
			}
			catch ( \Exception $e ) {
			}
		}
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