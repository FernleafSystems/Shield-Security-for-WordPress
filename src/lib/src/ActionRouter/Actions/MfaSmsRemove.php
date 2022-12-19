<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaSmsRemove extends MfaBase {

	public const SLUG = 'mfa_profile_sms_remove';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$available = $mod->getMfaController()->getProvidersAvailableToUser( Services::WpUsers()->getCurrentWpUser() );
		/** @var Sms $provider */
		$provider = $available[ Sms::ProviderSlug() ];
		$provider->removeFromProfile();

		$this->response()->action_response_data = [
			'success'     => true,
			'message'     => __( 'SMS Registration Removed', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}