<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\WebAuthN;

class MfaWebauthnRemoveSource extends MfaUserConfigBase {

	public const SLUG = 'mfa_webauthn_remove_source';

	protected function exec() {
		/** @var WebAuthN $provider */
		$provider = self::con()
						->getModule_LoginGuard()
						->getMfaController()
						->getProvidersAvailableToUser( $this->getActiveWPUser() )[ WebAuthN::ProviderSlug() ];
		$provider->deleteSource( $this->action_data[ 'wan_source_id' ] ?? '' );

		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'Authenticator removed from your profile', 'wp-simple-firewall' ),
		];
	}
}