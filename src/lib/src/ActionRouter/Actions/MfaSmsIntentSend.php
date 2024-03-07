<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;

class MfaSmsIntentSend extends MfaUserConfigBase {

	public const SLUG = 'intent_sms_send';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var Sms $provider */
		$provider = $available[ Sms::ProviderSlug() ];
		try {
			$provider->startLoginIntent();
			$response = [
				'success'     => true,
				'message'     => __( 'One-Time Password was sent to your phone.', 'wp-simple-firewall' ),
				'page_reload' => true
			];
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'message'     => $e->getMessage(),
				'page_reload' => true
			];
		}

		$this->response()->action_response_data = $response;
	}
}