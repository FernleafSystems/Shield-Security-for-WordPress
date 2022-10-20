<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;

class SecurityAdminLogin extends SecurityAdminBase {

	const SLUG = 'sec_admin_login';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$resp = $this->response();

		$resp->success = $mod->getSecurityAdminController()->verifyPinRequest();
		$html = '';
		if ( $resp->success ) {
			$resp->message = __( 'Security Admin PIN Accepted.', 'wp-simple-firewall' )
							 .' '.__( 'Please wait', 'wp-simple-firewall' ).' ...';
		}
		else {
			$remaining = ( new QueryRemainingOffenses() )
				->setMod( $this->getCon()->getModule_IPs() )
				->setIP( $this->getCon()->this_req->ip )
				->run();
			$resp->message = __( 'Security Admin PIN incorrect.', 'wp-simple-firewall' ).' ';
			if ( $remaining > 0 ) {
				$resp->message .= sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $remaining );
			}
			else {
				$resp->message .= __( "No attempts remaining.", 'wp-simple-firewall' );
			}
		}

		$resp->action_response_data = [
			'html'        => $html,
			'page_reload' => true,
		];
	}
}