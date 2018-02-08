<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Cooldown', false ) ) {
	return;
}

require_once( dirname(__FILE__ ).'/loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getFeature();

		// We give it a priority of 10 so that we can jump in before WordPress does its own validation.
		add_filter( 'authenticate', array( $this, 'checkLoginInterval' ), 10, 1 );

		// apply to user registrations if set to do so.
		if ( $oFO->getIsCheckingUserRegistrations() ) {
			add_filter( 'registration_errors', array( $this, 'checkLoginInterval' ), 10, 2 );
		}
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 *
	 * @param null|WP_User|WP_Error $oUserOrError
	 * @return WP_User|WP_Error
	 */
	public function checkLoginInterval( $oUserOrError ) {
		if ( !$this->loadWp()->isRequestUserLogin() ) {
			return $oUserOrError;
		}

		// If we're outside the interval, let the login process proceed as per normal and
		// update our last login time.
		$bWithinCooldownPeriod = $this->getIsWithinCooldownPeriod();
		if ( !$bWithinCooldownPeriod ) {
			$this->updateLastLoginTime();
			$this->doStatIncrement( 'login.cooldown.success' );
			return $oUserOrError;
		}

		// At this point someone has attempted to login within the previous login wait interval
		// So we remove WordPress's authentication filter and our own user check authentication
		// And finally return a WP_Error which will be reflected back to the user.

		$sErrorString = _wpsf__( "Login Cooldown in effect." ).' '
			.sprintf(
				_wpsf__( "You must wait %s seconds before attempting to %s again." ),
				$this->getLoginCooldownInterval() - $this->getSecondsSinceLastLogin(),
				$this->loadWp()->isRequestUserLogin() ? _wpsf__( 'login' ) : _wpsf__( 'register' )
			);

		if ( !is_wp_error( $oUserOrError ) ) {
			$oUserOrError = new WP_Error();
		}
		$oUserOrError->add( 'wpsf_logininterval', $sErrorString );

		$this->setLoginAsFailed( 'login.cooldown.fail' );

		return $oUserOrError;
	}

	/**
	 * @return int
	 */
	protected function getLoginCooldownInterval() {
		return (int)$this->getOption( 'login_limit_interval' );
	}

	/**
	 * @return int
	 */
	protected function getLastLoginTime() {
		$sFile = $this->getLastLoginTimeFilePath();
		return $this->loadFS()->exists( $sFile ) ? filemtime( $sFile ) : 0;
	}

	/**
	 * @return string
	 */
	protected function getLastLoginTimeFilePath() {
		return self::getController()->getRootDir().'mode.login_throttled';
	}

	/**
	 */
	protected function updateLastLoginTime() {
		$this->loadFS()->touch( $this->getLastLoginTimeFilePath(), $this->time() );
	}

	/**
	 * @return bool
	 */
	protected function getIsWithinCooldownPeriod() {
		// Is there an interval set?
		$nCooldown = $this->getLoginCooldownInterval();
		if ( empty( $nCooldown ) || $nCooldown <= 0 ) {
			return false;
		}

		return ( $this->getSecondsSinceLastLogin() < $nCooldown );
	}

	/**
	 * @return int
	 */
	protected function getSecondsSinceLastLogin() {
		return ( $this->time() - $this->getLastLoginTime() );
	}
}