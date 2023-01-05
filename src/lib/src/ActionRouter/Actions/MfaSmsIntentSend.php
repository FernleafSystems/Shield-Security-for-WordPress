<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;

class MfaSmsIntentSend extends MfaBase {

	public const SLUG = 'intent_sms_send';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$available = $mod->getMfaController()->getProvidersAvailableToUser( $this->getActiveWPUser() );
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