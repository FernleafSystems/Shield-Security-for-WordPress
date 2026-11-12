<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;

class MfaEmailToggle extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_toggle_email';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var ?Email $provider */
		$provider = $available[ Email::ProviderSlug() ] ?? null;
		if ( !empty( $provider ) && !$provider->isEnforced() ) {
			// Only an explicit "on" or "off" changes the setting; anything else refuses the request.
			$turnOn = $this->fields()->oneOf( 'direction', [ 'on', 'off' ] ) === 'on';
			$provider->toggleEmail2FA( $turnOn );
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

		$this->response()->setPayload( [
			'message' => $msg,
		] )->setPayloadSuccess( $success );
	}
}
