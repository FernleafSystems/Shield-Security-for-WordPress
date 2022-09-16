<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\U2F;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaU2fAdd extends MfaBase {

	const SLUG = 'mfa_profile_u2f_add';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var U2F $provider */
		$provider = $mod->getMfaController()->getProviders()[ U2F::SLUG ];

		$u2fReg = Services::Request()->post( 'icwp_wpsf_new_u2f_response' );
		if ( empty( $u2fReg ) ) {
			$response = [
				'success'     => false,
				'message'     => __( 'U2F registration details were missing in the request.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		else {
			$result = $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
							   ->addNewRegistration( $u2fReg );
			$response = [
				'success'     => $result->success,
				'message'     => $result->success ? $result->msg_text : $result->error_text,
				'page_reload' => true
			];
		}

		$this->response()->action_response_data = $response;
	}
}