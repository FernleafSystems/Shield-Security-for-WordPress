<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\WebAuthN;

class MfaWebauthnRegistrationStart extends MfaUserConfigBase {

	public const SLUG = 'mfa_webauthn_registration_start';

	protected function exec() {

		$response = [
			'success'     => false,
			'page_reload' => false
		];

		$user = $this->getActiveWPUser();
		if ( empty( $user ) ) {
			$response[ 'message' ] = __( "User must be logged-in.", 'wp-simple-firewall' );
		}
		else {
			$available = $this->con()
							  ->getModule_LoginGuard()
							  ->getMfaController()
							  ->getProvidersAvailableToUser( $user );
			/** @var WebAuthN $provider */
			$provider = $available[ WebAuthN::ProviderSlug() ] ?? null;

			if ( empty( $provider ) ) {
				$response[ 'message' ] = __( "WebAuthN isn't available for this user.", 'wp-simple-firewall' );
			}
			else {
				try {
					$response = [
						'success'     => true,
						'challenge' => $provider->startNewRegistrationRequest(),
						'page_reload' => false
					];
				}
				catch ( \Exception $e ) {
					$response[ 'message' ] = __( "There was a problem preparing the WebAuthN Registration Challenge.", 'wp-simple-firewall' );
				}
			}
		}

		$this->response()->action_response_data = $response;
	}
}