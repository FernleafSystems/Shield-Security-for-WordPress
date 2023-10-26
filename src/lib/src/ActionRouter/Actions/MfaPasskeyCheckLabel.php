<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

/**
 * TODO
 */
class MfaPasskeyCheckLabel extends MfaUserConfigBase {

	public const SLUG = 'mfa_passkey_check_label';

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
			/** @var Passkey $provider */
			$provider = $available[ Passkey::ProviderSlug() ] ?? null;

			if ( empty( $provider ) ) {
				$response[ 'message' ] = __( "Passkey authentication isn't available for this user.", 'wp-simple-firewall' );
			}
			else {
				try {
					$response = [
						'success'     => true,
						'challenge' => $provider->startNewRegistrationRequest( $this->action_data[ 'label' ] ?? '' ),
						'page_reload' => false
					];
				}
				catch ( \Exception $e ) {
					$response[ 'message' ] = __( "There was a problem preparing the Passkey Registration Challenge.", 'wp-simple-firewall' );
				}
			}
		}

		$this->response()->action_response_data = $response;
	}
}