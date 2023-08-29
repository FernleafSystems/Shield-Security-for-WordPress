<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class MfaCanEmailSendVerify extends MfaUserConfigBase {

	public const SLUG = 'email_send_verify';

	protected function exec() {
		$opts = self::con()->getModule_LoginGuard()->getOptions();

		if ( $opts->getOpt( 'email_can_send_verified_at' ) < 1 ) {
			$opts->setOpt( 'email_can_send_verified_at', Services::Request()->ts() );
			self::con()->getModule_LoginGuard()->saveModOptions();
			self::con()
				->getAdminNotices()
				->addFlash(
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