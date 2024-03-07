<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

class MfaPasskeyAuthenticationStart extends MfaUserConfigBase {

	use AuthNotRequired;

	public const SLUG = 'mfa_passkey_auth_start';

	protected function exec() {

		$response = [
			'success'     => false,
			'page_reload' => false
		];

		$user = $this->getActiveWPUser();
		if ( empty( $user ) ) {
			$response[ 'message' ] = __( 'User must be logged-in.', 'wp-simple-firewall' );
		}
		else {
			$available = self::con()->comps->mfa->getProvidersAvailableToUser( $user );
			/** @var Passkey $provider */
			$provider = $available[ Passkey::ProviderSlug() ] ?? null;

			if ( empty( $provider ) ) {
				$response[ 'message' ] = __( "Passkeys aren't available for this user.", 'wp-simple-firewall' );
			}
			else {
				try {
					$response = [
						'success'     => true,
						'challenge'   => $provider->startNewAuth(),
						'page_reload' => false
					];
				}
				catch ( \Exception $e ) {
					$response[ 'message' ] = __( "There was a problem preparing the Passkey Auth Challenge.", 'wp-simple-firewall' );
				}
			}
		}

		$this->response()->action_response_data = $response;
	}
}