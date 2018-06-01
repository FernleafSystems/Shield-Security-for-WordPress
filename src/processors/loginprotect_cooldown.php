<?php

if ( class_exists( 'ICWP_WPSF_Processor_LoginProtect_Cooldown', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/loginprotect_base.php' );

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @var bool
	 */
	private $bCooldownUpdated = false;

	/**
	 * @throws Exception
	 */
	protected function performCheckWithException() {

		if ( !$this->isCooldownAlreadyUpdated() ) {

			$bWithinCooldownPeriod = $this->isWithinCooldownPeriod();
			$this->updateLastLoginTime();

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			if ( $bWithinCooldownPeriod ) {

				$sErrorString = _wpsf__( "Login Cooldown in effect." ).' '
								.sprintf(
									_wpsf__( "You must wait %s seconds before attempting this action again." ),
									$this->getLoginCooldownInterval() - $this->getSecondsSinceLastLogin()
								);

				$this->setLoginAsFailed( 'login.cooldown.fail' );
				$this->addToAuditEntry( _wpsf__( 'Cooldown triggered and request (login/register/lost-password) was blocked.' ) );
				throw new Exception( $sErrorString );
			}
			else {
				$this->doStatIncrement( 'login.cooldown.success' );
			}
		}
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
		$this->bCooldownUpdated = true;
		$this->loadFS()->deleteFile( $this->getLastLoginTimeFilePath() );
		$this->loadFS()->touch( $this->getLastLoginTimeFilePath(), $this->time() );
	}

	/**
	 * @return bool
	 */
	private function isWithinCooldownPeriod() {
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

	/**
	 * @return bool
	 */
	protected function isCooldownAlreadyUpdated() {
		return (bool)$this->bCooldownUpdated;
	}
}