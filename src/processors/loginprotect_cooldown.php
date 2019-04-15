<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( !$this->isFactorTested() ) {

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			$nRemaining = $oFO->getCooldownInterval() - $this->getSecondsSinceLastLogin();
			if ( $nRemaining > 0 ) {
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
	private function getSecondsSinceLastLogin() {
		$sFile = $this->getLastLoginTimeFilePath();
		$nLastLogin = Services::WpFs()->exists( $sFile ) ? filemtime( $sFile ) : 0;
		return ( Services::Request()->ts() - $nLastLogin );
	}

	/**
	 * @return string
	 */
	private function getLastLoginTimeFilePath() {
		return path_join( $this->getCon()->getRootDir(), 'mode.login_throttled' );
	}

	/**
	 * @return $this
	 */
	private function updateLastLoginTime() {
		Services::WpFs()->deleteFile( $this->getLastLoginTimeFilePath() );
		Services::WpFs()->touch( $this->getLastLoginTimeFilePath(), $this->time() );
		return $this;
	}
}