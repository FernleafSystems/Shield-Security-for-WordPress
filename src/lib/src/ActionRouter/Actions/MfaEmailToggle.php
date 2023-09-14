<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailToggle extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_toggle_email';

	protected function exec() {
		$available = self::con()
						 ->getModule_LoginGuard()
						 ->getMfaController()
						 ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var ?Email $provider */
		$provider = $available[ Email::ProviderSlug() ] ?? null;
		if ( !empty( $provider ) && !$provider->isEnforced() ) {
			$turnOn = Services::Request()->post( 'direction' ) === 'on';
			$provider->setProfileValidated( $turnOn );
			$success = $turnOn === $provider->isProfileActive();

			if ( $success ) {
				$msg = $turnOn ? __( 'Email 2FA activated.', 'wp-simple-firewall' )
					: __( 'Email 2FA deactivated.', 'wp-simple-firewall' );
			}
			else {
				$msg = __( "Email 2FA settings couldn't be changed.", 'wp-simple-firewall' );
			}
		}
		else {
			$success = false;
			$msg = __( "Changing 2FA Email options isn't currently available to you.", 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true
		];
	}
}