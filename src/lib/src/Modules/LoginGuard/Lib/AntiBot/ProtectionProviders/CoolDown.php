<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\CooldownFlagFile;

class CoolDown extends BaseProtectionProvider {

	/**
	 * @inheritDoc
	 */
	public function performCheck( $oForm ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			$oCooldownFlag = ( new CooldownFlagFile() )->setMod( $this->getMod() );
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

	/**
	 * @inheritDoc
	 */
	public function buildFormInsert( $oFormProvider ) {
		return '';
	}
}