<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class MfaCanEmailSendVerify extends MfaUserConfigBase {

	public const SLUG = 'email_send_verify';

	protected function exec() {
		$con = self::con();
		if ( $con->opts->optGet( 'email_can_send_verified_at' ) < 1 ) {
			$con->opts
				->optSet( 'email_can_send_verified_at', Services::Request()->ts() )
				->store();
			$con->admin_notices->addFlash(
				__( 'Email verification completed successfully.', 'wp-simple-firewall' ),
				$this->getActiveWPUser()
			);
		}

		$this->response()->action_response_data = [
			'success'  => true,
			'message'  => __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			'redirect' => self::con()->plugin_urls->adminHome()
		];
	}
}