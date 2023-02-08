<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\U2F;
use FernleafSystems\Wordpress\Services\Services;

class MfaU2fAdd extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_u2f_add';

	protected function exec() {
		$available = $this->getCon()
						  ->getModule_LoginGuard()
						  ->getMfaController()
						  ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var U2F $provider */
		$provider = $available[ U2F::ProviderSlug() ];

		$u2fReg = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( empty( $u2fReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'U2F registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->addNewRegistration( $u2fReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true
			];
		}

		$this->response()->action_response_data = $response;
	}
}