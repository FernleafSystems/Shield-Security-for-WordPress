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
			$this->setFactorTested( true );

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			$nRemaining = $oFO->getCooldownInterval() - $this->getSecondsSinceLastLogin();
			if ( $nRemaining > 0 ) {
				$sErrorString = __( "Request Cooldown in effect.", 'wp-simple-firewall' ).' '
								.sprintf(
									__( "You must wait %s seconds before attempting this action again.", 'wp-simple-firewall' ),
									$nRemaining
								);

				$this->getCon()->fireEvent( 'cooldown_fail' );
				$this->processFailure();
				throw new \Exception( $sErrorString );
			}

			$this->updateLastLoginTime();
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
		Services::WpFs()->touch( $this->getLastLoginTimeFilePath(), Services::Request()->ts() );
		return $this;
	}
}