<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

/**
 * Compatibility endpoint for clients that submit passkey authentication over AJAX
 * instead of the current login form POST path.
 */
class MfaPasskeyAuthenticationVerify extends MfaLoginFlowBase {

	public const SLUG = 'mfa_passkey_auth_verify';

	protected function exec() {
		$response = [
			'success'     => false,
			'page_reload' => false
		];

		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getLoginWPUser() );
		/** @var ?Passkey $provider */
		$provider = $available[ Passkey::ProviderSlug() ] ?? null;

		$wanReg = $this->action_data[ 'auth' ];
		if ( !$provider instanceof Passkey ) {
			$response[ 'message' ] = __( "Passkeys aren't available for this user.", 'wp-simple-firewall' );
		}
		elseif ( !\is_string( $wanReg ) || $wanReg === '' ) {
			$response[ 'message' ] = __( 'Passkey authentication details were missing in the request.', 'wp-simple-firewall' );
			$response[ 'page_reload' ] = true;
		}
		else {
			$result = $provider->verifyAuthResponse( $wanReg );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$payloadSuccess = $response[ 'success' ];
		unset( $response[ 'success' ] );

		$this->response()
			 ->setPayload( $response )
			 ->setPayloadSuccess( $payloadSuccess );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'login_wp_user',
			'login_nonce',
			'auth',
		];
	}
}
