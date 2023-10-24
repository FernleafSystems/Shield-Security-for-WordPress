<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\WebAuthN;

class MfaWebauthnRegistrationVerify extends MfaUserConfigBase {

	public const SLUG = 'mfa_webauthn_registration_verify';

	protected function exec() {
		$available = $this->con()
						  ->getModule_LoginGuard()
						  ->getMfaController()
						  ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var WebAuthN $provider */
		$provider = $available[ WebAuthN::ProviderSlug() ];

		$wanAuth = $this->action_data[ 'reg' ];

		if ( empty( $wanAuth ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'WebAuthN registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->verifyNewRegistration( $wanAuth );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$this->response()->action_response_data = $response;
	}
}