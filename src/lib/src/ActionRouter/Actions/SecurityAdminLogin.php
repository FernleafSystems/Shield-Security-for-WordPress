<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\{
	ToggleSecAdminStatus,
	VerifyPinRequest
};
use FernleafSystems\Wordpress\Services\Services;

class SecurityAdminLogin extends SecurityAdminBase {

	public const SLUG = 'sec_admin_login';

	protected function exec() {
		$resp = $this->response();

		if ( $this->con()->getModule_SecAdmin()->getSecurityAdminController()->isCurrentlySecAdmin() ) {
			$resp->success = true;
			$resp->message = __( "You're already a Security Admin.", 'wp-simple-firewall' )
							 .' '.__( 'Please wait a moment', 'wp-simple-firewall' ).' ...';
		}
		else {
			$resp->success = ( new VerifyPinRequest() )->run( (string)Services::Request()->post( 'sec_admin_key' ) );

			if ( $resp->success ) {
				( new ToggleSecAdminStatus() )->turnOn();
				$resp->message = __( 'Security Admin PIN Accepted.', 'wp-simple-firewall' )
								 .' '.__( 'Reloading', 'wp-simple-firewall' ).' ...';
			}
			else {
				$remaining = ( new QueryRemainingOffenses() )
					->setIP( $this->con()->this_req->ip )
					->run();
				$resp->message = __( 'Security Admin PIN incorrect.', 'wp-simple-firewall' ).' ';
				$resp->message .= $remaining > 0 ?
					sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $remaining )
					: __( "No attempts remaining.", 'wp-simple-firewall' );
			}
		}

		$resp->action_response_data = [
			'html'        => '',
			'page_reload' => true,
		];
	}
}