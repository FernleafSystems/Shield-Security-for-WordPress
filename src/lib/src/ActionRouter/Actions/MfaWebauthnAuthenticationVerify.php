<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\WebAuthN;

class MfaWebauthnAuthenticationVerify extends MfaUserConfigBase {

	use AuthNotRequired;

	public const SLUG = 'mfa_webauthn_auth_verify';

	protected function exec() {
		$available = $this->con()
						  ->getModule_LoginGuard()
						  ->getMfaController()
						  ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var WebAuthN $provider */
		$provider = $available[ WebAuthN::ProviderSlug() ];

		$wanReg = $this->action_data[ 'auth' ];
		error_log( var_export( $wanReg, true ) );
		if ( empty( $wanReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'WebAuthN authentication details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->verifyAuthResponse( $wanReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$this->response()->action_response_data = $response;
	}
}