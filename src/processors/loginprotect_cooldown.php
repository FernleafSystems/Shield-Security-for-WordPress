<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

class ICWP_WPSF_Processor_LoginProtect_Cooldown extends ICWP_WPSF_Processor_LoginProtect_Base {

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {

		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			$oCooldownFlag = ( new LoginGuard\Lib\CooldownFlagFile() )->setMod( $this->getMod() );
			if ( $oCooldownFlag->isWithinCooldownPeriod() ) {
				$sErrorString = __( "Request Cooldown in effect.", 'wp-simple-firewall' ).' '
								.sprintf(
									__( "You must wait %s seconds before attempting this action again.", 'wp-simple-firewall' ),
									$oCooldownFlag->getCooldownRemaining()
								);

				$this->getCon()->fireEvent( 'cooldown_fail' );
				$this->processFailure();
				throw new \Exception( $sErrorString );
			}

			$oCooldownFlag->updateCooldownFlag();
		}
	}
}