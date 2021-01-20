<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class UserSessionHandler {

	use ModConsumer;
	use OneTimeExecute;
	use WpLoginCapture;

	protected function canRun() {
		return $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions()
					->isReady();
	}

	protected function run() {
		$this->setupLoginCaptureHooks();
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100, 1 );
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
		/** @var UserManagement\ModCon $mod */
		$mod = $this->getMod();
		try {
			if ( $mod->hasValidRequestIP() ) {
				$this->assessSession();
			}
		}
		catch ( \Exception $e ) {
			// We force-refresh the server IPs just to be sure.
			$srvIP = Services::IP();
			$srvIP->getServerPublicIPs( true );
			if ( !$srvIP->isLoopback() ) {
				$event = $e->getMessage();
				$con->fireEvent( $event );
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
					 ->getCurrent();
		if ( !$sess instanceof EntryVO ) {
			throw new \Exception( 'session_notfound' );
		}

		$ts = Services::Request()->ts();

		if ( $opts->hasMaxSessionTimeout() && ( $ts - $sess->logged_in_at > $opts->getMaxSessionTime() ) ) {
			throw new \Exception( 'session_expired' );
		}

		if ( $opts->hasSessionIdleTimeout() && ( $ts - $sess->last_activity_at > $opts->getIdleTimeoutInterval() ) ) {
			throw new \Exception( 'session_idle' );
		}

		$srvIP = Services::IP();
		if ( $opts->isLockToIp() && $srvIP->getRequestIp() != $sess->ip ) {
			throw new \Exception( 'session_iplock' );
		}
		// TODO: 'session_browserlock';
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

	/**
	 * @param \WP_User $user
	 */
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
					$sMessage = __( 'Your session has expired.', 'wp-simple-firewall' );
					break;

				case 'session_idle':
					$sMessage = __( 'Your session was idle for too long.', 'wp-simple-firewall' );
					break;

				case 'session_iplock':
					$sMessage = __( 'Your session was locked to another IP Address.', 'wp-simple-firewall' );
					break;

				case 'session_notfound':
					$sMessage = sprintf(
						__( 'You do not currently have a %s user session.', 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					);
					break;

				case 'session_browserlock':
					$sMessage = __( 'Your browser appears to have changed for this session.', 'wp-simple-firewall' );
					break;

				case 'session_unverified':
				default:
					$sMessage = __( 'Your session was terminated.', 'wp-simple-firewall' );
					break;
			}

			$sMessage .= '<br />'.__( 'Please login again.', 'wp-simple-firewall' );
			$error->add( 'shield-forcelogout', $sMessage );
		}
		return $error;
	}
}