<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\U2F;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaU2fRemove extends MfaBase {

	const SLUG = 'mfa_profile_u2f_remove';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var U2F $provider */
		$provider = $mod->getMfaController()
						->getProviders()[ U2F::SLUG ];

		$key = Services::Request()->post( 'u2fid' );
		if ( !empty( $key ) ) {
			$provider->setUser( Services::WpUsers()->getCurrentWpUser() )
					 ->removeRegisteredU2fId( $key );
		}

		$this->response()->action_response_data = [
			'success'     => !empty( $key ),
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}