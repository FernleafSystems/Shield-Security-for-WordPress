<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Yubikey;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaYubikeyToggle extends MfaBase {

	public const SLUG = 'mfa_profile_yubi_toggle';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;

		$available = $mod->getMfaController()->getProvidersAvailableToUser( Services::WpUsers()->getCurrentWpUser() );
		/** @var Yubikey $provider */
		$provider = $available[ Yubikey::ProviderSlug() ];
		$otp = Services::Request()->post( 'otp', '' );
		$result = $provider->toggleRegisteredYubiID( $otp );

		$this->response()->action_response_data = [
			'success'     => $result->success,
			'message'     => $result->success ? $result->msg_text : $result->error_text,
			'page_reload' => true
		];
	}
}