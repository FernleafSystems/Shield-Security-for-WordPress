<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\U2F;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 18.5
 */
class MfaU2fRemove extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_u2f_remove';

	protected function exec() {
		$available = self::con()
						 ->getModule_LoginGuard()
						 ->getMfaController()
						 ->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var U2F $provider */
		$provider = $available[ U2F::ProviderSlug() ];

		$key = Services::Request()->post( 'u2fid' );
		if ( !empty( $key ) ) {
			$provider->removeRegisteredU2fId( $key );
		}

		$this->response()->action_response_data = [
			'success'     => !empty( $key ),
			'message'     => __( 'Registered U2F device removed from profile.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}