<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerification;

class MfaCanEmailSendVerify extends MfaUserConfigBase {

	public const SLUG = 'email_send_verify';

	protected function exec() {
		$con = self::con();
		( new EmailDeliveryVerification() )->markVerified();
		$con->opts->store();
		$con->admin_notices->addFlash(
			__( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			$this->getActiveWPUser()
		);

		$this->response()->setPayload( [
			'message'  => __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			'redirect' => self::con()->plugin_urls->adminHome()
		] )->setPayloadSuccess( true );
	}
}
