<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

class MfaPasskeyAuthenticationStart extends MfaLoginFlowBase {

	public const SLUG = 'mfa_passkey_auth_start';

	protected function exec() {
		$response = [
			'success'     => false,
			'page_reload' => false
		];

		try {
			$user = $this->getLoginWPUser();

			$available = self::con()->comps->mfa->getProvidersAvailableToUser( $user );
			/** @var Passkey $provider */
			$provider = $available[ Passkey::ProviderSlug() ] ?? null;

			if ( empty( $provider ) ) {
				$response[ 'message' ] = __( "Passkeys aren't available for this user.", 'wp-simple-firewall' );
			}
			else {
				$response = [
					'success'     => true,
					'challenge'   => $provider->startNewAuth(),
					'page_reload' => false
				];
			}
		}
		catch ( ActionException $e ) {
			$response[ 'message' ] = $e->getMessage();
		}
		catch ( \Exception $e ) {
			$response[ 'message' ] = __( 'There was a problem preparing the Passkey Auth Challenge.', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = $response;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'login_wp_user',
			'login_nonce',
		];
	}
}