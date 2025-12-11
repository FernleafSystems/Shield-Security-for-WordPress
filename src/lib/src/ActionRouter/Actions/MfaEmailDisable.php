<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class MfaEmailDisable extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_disable';

	protected function exec() {
		self::con()->opts->optSet( 'enable_email_authentication', 'N' );
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
		];
	}
}