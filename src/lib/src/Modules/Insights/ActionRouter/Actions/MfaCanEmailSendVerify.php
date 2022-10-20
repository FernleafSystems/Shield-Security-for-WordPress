<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaCanEmailSendVerify extends MfaBase {

	const SLUG = 'email_send_verify';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$opts = $this->primary_mod->getOptions();

		if ( $opts->getOpt( 'email_can_send_verified_at' ) < 1 ) {
			$opts->setOpt( 'email_can_send_verified_at', Services::Request()->ts() );
			$mod->saveModOptions();
			if ( Services::WpUsers()->isUserLoggedIn() ) {
				$mod->setFlashAdminNotice( __( 'Email verification completed successfully.', 'wp-simple-firewall' ) );
			}
		}

		$this->response()->action_response_data = [
			'success'  => true,
			'message'  => __( 'Email verification completed successfully.', 'wp-simple-firewall' ),
			'redirect' => $this->getCon()->getPluginUrl_DashboardHome()
		];
	}
}