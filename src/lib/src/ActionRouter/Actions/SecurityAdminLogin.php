<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;

class SecurityAdminLogin extends SecurityAdminBase {

	public const SLUG = 'sec_admin_login';

	protected function exec() {
		$con = $this->getCon();
		$mod = $con->getModule_SecAdmin();
		$resp = $this->response();

		$html = '';
		if ( $mod->getSecurityAdminController()->isCurrentlySecAdmin() ) {
			$resp->success = true;
			$resp->message = __( "You're already a Security Admin.", 'wp-simple-firewall' )
							 .' '.__( 'Please wait a moment', 'wp-simple-firewall' ).' ...';
		}
		else {
			$resp->success = $mod->getSecurityAdminController()->verifyPinRequest();

			if ( $resp->success ) {
				( new ToggleSecAdminStatus() )
					->setMod( $mod )
					->turnOn();
				$resp->message = __( 'Security Admin PIN Accepted.', 'wp-simple-firewall' )
								 .' '.__( 'Please wait a moment', 'wp-simple-firewall' ).' ...';
			}
			else {
				$remaining = ( new QueryRemainingOffenses() )
					->setMod( $con->getModule_IPs() )
					->setIP( $con->this_req->ip )
					->run();
				$resp->message = __( 'Security Admin PIN incorrect.', 'wp-simple-firewall' ).' ';
				if ( $remaining > 0 ) {
					$resp->message .= sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $remaining );
				}
				else {
					$resp->message .= __( "No attempts remaining.", 'wp-simple-firewall' );
				}
			}
		}

		$resp->action_response_data = [
			'html'        => $html,
			'page_reload' => true,
		];
	}
}