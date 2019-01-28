<?php

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {

		if ( !$this->isFactorTested() ) {

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			if ( $this->isWithinCooldownPeriod() ) {

				$nRemaining = $this->getCooldownInterval() - $this->getSecondsSinceLastLogin();
				$sErrorString = _wpsf__( "Request Cooldown in effect." ).' '
								.sprintf(
									_wpsf__( "You must wait %s seconds before attempting this action again." ),
									$nRemaining
								);

				$this->setLoginAsFailed( 'login.cooldown.fail' )
					 ->addToAuditEntry( _wpsf__( 'Cooldown triggered and request (login/register/lost-password) was blocked.' ) );
				throw new \Exception( $sErrorString );
			}
			else {
				$this->updateLastLoginTime()
					 ->setFactorTested( true )
					 ->doStatIncrement( 'login.cooldown.success' );
			}
		}
	}

	/**
	 * @return int
	 */
	protected function getCooldownInterval() {
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
		return path_join( $this->getCon()->getRootDir(), 'mode.login_throttled' );
	}

	/**
	 * @return $this
	 */
	protected function updateLastLoginTime() {
		$this->loadFS()->deleteFile( $this->getLastLoginTimeFilePath() );
		$this->loadFS()->touch( $this->getLastLoginTimeFilePath(), $this->time() );
		return $this;
	}

	/**
	 * @return bool
	 */
	private function isWithinCooldownPeriod() {
		// Is there an interval set?
		$nCooldown = $this->getCooldownInterval();
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