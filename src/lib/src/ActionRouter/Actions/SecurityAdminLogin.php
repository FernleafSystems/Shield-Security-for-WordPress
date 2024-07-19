<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\{
	ToggleSecAdminStatus,
	VerifyPinRequest
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class SecurityAdminLogin extends SecurityAdminBase {

	public const SLUG = 'sec_admin_login';

	protected function exec() {
		$resp = $this->response();

		if ( self::con()->comps->sec_admin->isCurrentlySecAdmin() ) {
			$resp->success = true;
			$resp->message = __( "You're already a Security Admin.", 'wp-simple-firewall' )
							 .' '.__( 'Please wait a moment', 'wp-simple-firewall' ).' ...';
		}
		else {
			$pin = $this->action_data[ 'sec_admin_key' ] ?? '';
			if ( empty( $pin ) ) {
				$pin = FormParams::Retrieve()[ 'sec_admin_key' ] ?? '';
			}
			$resp->success = ( new VerifyPinRequest() )->run( $pin );

			if ( $resp->success ) {
				( new ToggleSecAdminStatus() )->turnOn();
				$resp->message = \implode( ' ', [
					__( 'Security Admin PIN Accepted.', 'wp-simple-firewall' ),
					__( 'Reloading', 'wp-simple-firewall' ),
					'...'
				] );
			}
			else {
				$remaining = ( new QueryRemainingOffenses() )
					->setIP( self::con()->this_req->ip )
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