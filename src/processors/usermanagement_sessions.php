<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_UserManagement_Sessions extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		if ( $this->isReadyToRun() ) {
			parent::run();
			add_filter( 'wp_login_errors', [ $this, 'addLoginMessage' ] );
			add_filter( 'auth_cookie_expiration', [ $this, 'setMaxAuthCookieExpiration' ], 100, 1 );
		}
	}

	/**
	 * Cron callback
	 */
	public function runDailyCron() {
		$this->cleanExpiredSessions();
	}

	/**
	 * @return bool
	 */
	public function isReadyToRun() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		return ( parent::isReadyToRun() && $oFO->getSessionsProcessor()->isReadyToRun() );
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
		if ( $this->isReadyToRun() && Services::WpUsers()->isUserLoggedIn() && !Services::Rest()->isRest() ) {
			$this->checkCurrentSession();
		}
	}

	/**
	 */
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
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		if ( !$oFO->hasSession() ) {
			throw new \Exception( 'session_notfound' );
		}

		$oSess = $oFO->getSession();
		$nTime = Services::Request()->ts();

		// timeout interval
		if ( $oFO->hasMaxSessionTimeout() && ( $nTime - $oSess->logged_in_at > $oFO->getMaxSessionTime() ) ) {
			throw new \Exception( 'session_expired' );
		}

		if ( $oFO->hasSessionIdleTimeout() && ( $nTime - $oSess->last_activity_at > $oFO->getIdleTimeoutInterval() ) ) {
			throw new \Exception( 'session_idle' );
		}

		if ( $oFO->isLockToIp() ) {
			/** We allow the original session IP, the SERVER_ADDR, and the "what is my IP" */
			/** @var ICWP_WPSF_FeatureHandler_Plugin $oPluginMod */
			$oPluginMod = $this->getCon()->getModule( 'plugin' );
			$aPossibleIps = [
				$oSess->ip,
				Services::Request()->getServerAddress(),
				$oPluginMod->getMyServerIp()
			];
			if ( !in_array( $this->ip(), $aPossibleIps ) ) {
				throw new \Exception( 'session_iplock' );
			}
		}
		// TODO: 'session_browserlock';
	}

	public function cleanExpiredSessions() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Delete $oTerminator */
		$oTerminator = $oFO->getDbHandler_Sessions()->getQueryDeleter();

		// We use 14 as an outside case. If it's 2 days, WP cookie will expire anyway.
		// And if User Management is active, then it'll draw in that value.
		$oTerminator->forExpiredLoginAt( $this->getLoginExpiredBoundary() );

		// Default is ZERO, so we don't want to terminate all sessions if it's never set.
		if ( $oFO->hasSessionIdleTimeout() ) {
			$oTerminator->forExpiredLoginIdle( $this->getLoginIdleExpiredBoundary() );
		}
	}

	/**
	 * @return int
	 */
	public function countActiveSessions() {
		return $this->getActiveSessionsQuerySelector()->count();
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\EntryVO[]|mixed
	 */
	public function getActiveSessions() {
		return $this->getActiveSessionsQuerySelector()->query();
	}

	/**
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Select
	 */
	private function getActiveSessionsQuerySelector() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Select $oSel */
		$oSel = $oFO->getDbHandler_Sessions()->getQuerySelector();

		if ( $oFO->hasMaxSessionTimeout() ) {
			$oSel->filterByLoginNotExpired( $this->getLoginExpiredBoundary() );
		}
		if ( $oFO->hasSessionIdleTimeout() ) {
			$oSel->filterByLoginNotIdleExpired( $this->getLoginIdleExpiredBoundary() );
		}
		return $oSel;
	}

	/**
	 * @return int
	 */
	private function getLoginExpiredBoundary() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		return Services::Request()->ts() - $oFO->getMaxSessionTime();
	}

	/**
	 * @return int
	 */
	private function getLoginIdleExpiredBoundary() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		return $this->time() - $oFO->getIdleTimeoutInterval();
	}

	/**
	 * @param int $nTimeout
	 * @return int
	 */
	public function setMaxAuthCookieExpiration( $nTimeout ) {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		return $oFO->hasMaxSessionTimeout() ? min( $nTimeout, $oFO->getMaxSessionTime() ) : $nTimeout;
	}

	/**
	 * @param \WP_User $oUser
	 */
	protected function enforceSessionLimits( $oUser ) {

		$nSessionLimit = $this->getOption( 'session_username_concurrent_limit', 1 );
		if ( !$this->isLoginCaptured() && $nSessionLimit > 0 && $oUser instanceof WP_User ) {
			$this->setLoginCaptured();
			/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getMod();
			try {
				$oFO->getDbHandler_Sessions()
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

		if ( !$oError instanceof WP_Error ) {
			$oError = new WP_Error();
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
}