<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;

class MfaEmailDisable extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'mfa_email_disable';

	protected function exec() {
		self::con()->opts->optSet( 'enable_email_authentication', 'N' );
		( new EmailDeliveryVerification() )->clearSent();
		self::con()->opts->store();
		$this->response()->setPayload( [
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true,
		] )->setPayloadSuccess( true );
	}
}
