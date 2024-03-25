<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Passkey;

class MfaPasskeyRemoveSource extends MfaUserConfigBase {

	public const SLUG = 'mfa_passkey_remove_source';

	protected function exec() {
		$success = false;
		/** @var Passkey $provider */
		$provider = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() )[ Passkey::ProviderSlug() ] ?? null;
		if ( $provider ) {
			$success = $provider->deleteSource( $this->action_data[ 'wan_source_id' ] ?? '' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $success ? __( 'Passkey removed from your profile', 'wp-simple-firewall' )
				: __( 'There was a problem removing this passkey', 'wp-simple-firewall' ),
			'page_reload' => !$success,
		];
	}
}