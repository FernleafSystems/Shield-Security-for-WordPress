<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

class MfaPasskeyRegistrationVerify extends MfaUserConfigBase {

	public const SLUG = 'mfa_passkey_registration_verify';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var Passkey $provider */
		$provider = $available[ Passkey::ProviderSlug() ];

		$wanAuth = $this->action_data[ 'reg' ];

		if ( empty( $wanAuth ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'Passkey registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->verifyRegistrationResponse( $wanAuth, $this->action_data[ 'label' ] ?? '' );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$this->response()->action_response_data = $response;
	}
}