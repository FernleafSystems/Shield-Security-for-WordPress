<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailToggle extends MfaBase {

	public const SLUG = 'mfa_profile_toggle_email';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var Email $provider */
		$provider = $mod->getMfaController()->getProviders()[ Email::SLUG ];

		$turnOn = Services::Request()->post( 'direction' ) === 'on';
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
				 ->setProfileValidated( $turnOn );
		$success = $turnOn === $provider->isProfileActive();

		if ( $success ) {
			$msg = $turnOn ? __( 'Email 2FA activated.', 'wp-simple-firewall' )
				: __( 'Email 2FA deactivated.', 'wp-simple-firewall' );
		}
		else {
			$msg = __( "Email 2FA settings couldn't be changed.", 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => true
		];
	}
}