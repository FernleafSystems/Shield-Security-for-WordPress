<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Sms;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaSmsIntentSend extends MfaBase {

	const SLUG = 'intent_sms_send';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var Sms $provider */
		$provider = $mod->getMfaController()->getProviders()[ Sms::SLUG ];
		try {
			$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
					 ->startLoginIntent();
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