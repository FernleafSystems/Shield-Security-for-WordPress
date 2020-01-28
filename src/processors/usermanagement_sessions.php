<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_UserManagement_Sessions extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
		add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100, 1 );
	}

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		( new UserManagement\Lib\CleanExpired() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @param string   $sUsername
	 * @param \WP_User $oUser
	 */
	public function onWpLogin( $sUsername, $oUser ) {
		if ( !$oUser instanceof \WP_User ) {
			$oUser = Services::WpUsers()->getUserByUsername( $sUsername );
		}
		$this->enforceSessionLimits( $oUser );
	}

	/**
	 * @param string $sCookie
	 * @param int    $nExpire
	 * @param int    $nExpiration
	 * @param int    $nUserId
	 */
	public function onWpSetLoggedInCookie( $sCookie, $nExpire, $nExpiration, $nUserId ) {
		$this->enforceSessionLimits( Services::WpUsers()->getUserById( $nUserId ) );
	}

	/**
	 */
	public function onWpLoaded() {
		if ( Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	private function checkCurrentSession() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		try {
			$this->assessSession();
		}
		catch ( \Exception $oE ) {
			$sEvent = $oE->getMessage();
			$this->getCon()
				 ->fireEvent( $sEvent );
			$oMod->getSessionsProcessor()
				 ->terminateCurrentSession();
			$oU = Services::WpUsers();
			is_admin() ? $oU->forceUserRelogin( [ 'shield-forcelogout' => $sEvent ] ) : $oU->logoutUser( true );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function assessSession() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		$oSess = $oMod->getSession();
		if ( empty( $oSess ) ) {
			throw new \Exception( 'session_notfound' );
		}

		$nTime = Services::Request()->ts();

		// timeout interval
		if ( $oOpts->hasMaxSessionTimeout() && ( $nTime - $oSess->logged_in_at > $oOpts->getMaxSessionTime() ) ) {
			throw new \Exception( 'session_expired' );
		}

		if ( $oOpts->hasSessionIdleTimeout() && ( $nTime - $oSess->last_activity_at > $oOpts->getIdleTimeoutInterval() ) ) {
			throw new \Exception( 'session_idle' );
		}

		$oIP = Services::IP();
		if ( $oOpts->isLockToIp() && !$oIP->isLoopback() && $oIP->getRequestIp() != $oSess->ip ) {
			throw new \Exception( 'session_iplock' );
		}
		// TODO: 'session_browserlock';
	}

	/**
	 * @param int $nTimeout
	 * @return int
	 */
	public function setMaxAuthCookieExpiration( $nTimeout ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->hasMaxSessionTimeout() ? min( $nTimeout, $oOpts->getMaxSessionTime() ) : $nTimeout;
	}

	/**
	 * @param \WP_User $oUser
	 */
	protected function enforceSessionLimits( $oUser ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		$nSessionLimit = $oOpts->getOpt( 'session_username_concurrent_limit', 1 );
		if ( !$this->isLoginCaptured() && $nSessionLimit > 0 && $oUser instanceof WP_User ) {
			$this->setLoginCaptured();
			/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
			$oMod = $this->getMod();
			try {
				$oMod->getDbHandler_Sessions()
					 ->getQueryDeleter()
					 ->addWhere( 'wp_username', $oUser->user_login )
					 ->deleteExcess( $nSessionLimit, 'last_activity_at', true );
			}
			catch ( \Exception $oE ) {
			}
		}
	}

	/**
	 * @param \WP_Error $oError
	 * @return \WP_Error
	 */
	public function addLoginMessage( $oError ) {

		if ( !$oError instanceof \WP_Error ) {
			$oError = new \WP_Error();
		}

		$sForceLogout = Services::Request()->query( 'shield-forcelogout' );
		if ( $sForceLogout ) {

			switch ( $sForceLogout ) {
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
			$oError->add( 'shield-forcelogout', $sMessage );
		}
		return $oError;
	}

	/**
	 * @deprecated 8.5
	 */
	public function cleanExpiredSessions() {
	}

	/**
	 * @deprecated 8.5
	 */
	private function getLoginIdleExpiredBoundary() {
	}

	/**
	 * @deprecated 8.5
	 */
	private function getLoginExpiredBoundary() {
	}
}