<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;

class MfaCanEmailSendVerify extends MfaUserConfigBase {

	public const SLUG = 'email_send_verify';

	protected function exec() {
		$mod = $this->con()->getModule_LoginGuard();
		$opts = $mod->getOptions();

		if ( $opts->getOpt( 'email_can_send_verified_at' ) < 1 ) {
			$opts->setOpt( 'email_can_send_verified_at', Services::Request()->ts() );
			$mod->saveModOptions();
			$this->con()
				 ->getAdminNotices()
				 ->addFlash(
					 __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
					 $this->getActiveWPUser()
				 );
		}

		$this->response()->action_response_data = [
			'success'  => true,
			'message'  => __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			'redirect' => $this->con()->plugin_urls->adminHome()
		];
	}
}