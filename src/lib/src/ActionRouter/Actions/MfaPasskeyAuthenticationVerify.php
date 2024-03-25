<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

/**
 * Not currently used
 */
class MfaPasskeyAuthenticationVerify extends MfaUserConfigBase {

	use AuthNotRequired;

	public const SLUG = 'mfa_passkey_auth_verify';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var Passkey $provider */
		$provider = $available[ Passkey::ProviderSlug() ];

		$wanReg = $this->action_data[ 'auth' ];
		if ( empty( $wanReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'Passkey authentication details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->verifyAuthResponse( $wanReg );
			$response = [
				'success' => $result->success,
				'message' => $result->success ? $result->msg_text : $result->error_text,
			];
		}

		$this->response()->action_response_data = $response;
	}
}