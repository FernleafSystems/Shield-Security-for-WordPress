<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailSendIntent extends MfaBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'mfa_email_intent_code_send';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$req = Services::Request();

		$success = false;
		$userID = $req->post( 'wp_user_id' );
		$plainNonce = $req->post( 'login_nonce' );
		if ( !empty( $userID ) && !empty( $plainNonce ) ) {
			$user = Services::WpUsers()->getUserById( $userID );
			if ( $user instanceof \WP_User ) {
				/** @var Email $p */
				$p = $mod->getMfaController()->getProvidersActiveForUser( $user )[ Email::ProviderSlug() ] ?? null;
				$success = !empty( $p ) && $p->sendEmailTwoFactorVerify( $plainNonce );
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $success ?
				implode( " \n", [
					__( 'A new One-Time Password was sent to your email address.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
						__( 'Previously created One-Time Passwords are invalid.', 'wp-simple-firewall' ) )
				] )
				: __( 'There was a problem sending the One-Time Password email.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}