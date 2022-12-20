<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaCanEmailSendVerify extends MfaBase {

	public const SLUG = 'email_send_verify';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$opts = $this->primary_mod->getOptions();

		if ( $opts->getOpt( 'email_can_send_verified_at' ) < 1 ) {
			$opts->setOpt( 'email_can_send_verified_at', Services::Request()->ts() );
			$mod->saveModOptions();
			if ( Services::WpUsers()->isUserLoggedIn() ) {
				$this->getCon()
					 ->getAdminNotices()
					 ->addFlash(
						 __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
						 Services::WpUsers()->getCurrentWpUser()
					 );
			}
		}

		$this->response()->action_response_data = [
			'success'  => true,
			'message'  => __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			'redirect' => $this->getCon()->plugin_urls->adminHome()
		];
	}
}