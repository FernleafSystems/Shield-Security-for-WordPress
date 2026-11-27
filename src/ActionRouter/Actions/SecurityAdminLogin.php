<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\{
	ToggleSecAdminStatus,
	VerifyPinRequest
};

class SecurityAdminLogin extends SecurityAdminBase {

	public const SLUG = 'sec_admin_login';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$con = self::con();
		$reload = true;
		if ( $con->comps->sec_admin->isCurrentlySecAdmin() ) {
			$success = true;
			$message = __( "You're already a Security Admin.", 'wp-simple-firewall' )
					   .' '.__( 'Please wait a moment', 'wp-simple-firewall' ).' ...';
		}
		else {
			// The PIN arrives as a field of its own. A page still open from before the PIN box was replaced sends it
			// inside the submitted form, and its PIN is just as good.
			$pin = $this->fields()->string( 'sec_admin_key', '' );
			if ( $pin === '' ) {
				$pin = $this->formFields()->string( 'sec_admin_key', '' );
			}
			$success = ( new VerifyPinRequest() )->run( $pin );

			if ( $success ) {
				( new ToggleSecAdminStatus() )->turnOn();
				$message = \implode( ' ', [
					__( 'Security Admin PIN Accepted.', 'wp-simple-firewall' ),
					__( 'Reloading', 'wp-simple-firewall' ),
					'...'
				] );
			}
			else {
				$message = __( 'Security Admin PIN incorrect.', 'wp-simple-firewall' );
				// The PIN may be retried in place until the IP is about to be blocked; then reload to show the block.
				// Only public IPs collect offenses (ProcessOffenses), so any other IP may always retry.
				$reload = false;
				if ( $con->comps->opts_lookup->enabledIpAutoBlock() && $con->this_req->ip_is_public ) {
					$remaining = ( new QueryRemainingOffenses() )
						->setIP( $con->this_req->ip )
						->run();
					$reload = $remaining < 1;
					$message .= ' '.( $reload ?
							__( "No attempts remaining.", 'wp-simple-firewall' )
							/* translators: %s: number of login attempts remaining */
							: sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $remaining ) );
				}
			}
		}

		// No redirect: the PIN is entered on the page it unlocks, so reloading that page is the outcome wanted.
		$this->response()->setPayload( [
			'message'     => $message,
			'page_reload' => $reload,
		] )->setPayloadSuccess( $success );
	}
}
