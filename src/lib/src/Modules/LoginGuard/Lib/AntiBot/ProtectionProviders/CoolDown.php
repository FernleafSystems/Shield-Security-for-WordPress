<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\CooldownFlagFile;

/**
 * TODO: Cooldown needs integrated into the AntiBot system, and removed from this legacy implementation.
 */
class CoolDown extends BaseProtectionProvider {

	public function performCheck( $formProvider ) {
		if ( !$this->isFactorTested() ) {
			$this->setFactorTested( true );

			// At this point someone has attempted to login within the previous login wait interval
			// So we remove WordPress's authentication filter and our own user check authentication
			// And finally return a WP_Error which will be reflected back to the user.
			$cooldown = new CooldownFlagFile();
			if ( $cooldown->getCooldownRemaining() > 0 ) {
				$error = __( "Request Cooldown in effect.", 'wp-simple-firewall' ).' '
						 .sprintf(
							 __( "You must wait %s seconds before attempting this action again.", 'wp-simple-firewall' ),
							 $cooldown->getCooldownRemaining()
						 );

				self::con()->fireEvent( 'cooldown_fail' );
				$this->processFailure();
				throw new \Exception( $error );
			}

			$cooldown->updateCooldownFlag();
		}
	}

	public function buildFormInsert( $formProvider ) :string {
		return '';
	}
}