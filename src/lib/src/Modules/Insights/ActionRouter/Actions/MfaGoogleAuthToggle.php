<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\GoogleAuth;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaGoogleAuthToggle extends MfaBase {

	const SLUG = 'mfa_profile_toggle_ga';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var GoogleAuth $provider */
		$provider = $mod->getMfaController()->getProviders()[ GoogleAuth::SLUG ];
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() );

		$otp = Services::Request()->post( 'ga_otp', '' );
		$result = empty( $otp ) ? $provider->removeGA() : $provider->activateGA( $otp );

		$this->response()->action_response_data = [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}
}