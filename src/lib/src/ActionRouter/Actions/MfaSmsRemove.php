<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;

class MfaSmsRemove extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_sms_remove';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
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