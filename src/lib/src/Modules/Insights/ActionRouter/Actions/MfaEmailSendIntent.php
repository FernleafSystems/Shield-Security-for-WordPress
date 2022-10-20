<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailSendIntent extends MfaBase {

	use Traits\AuthNotRequired;

	const SLUG = 'mfa_email_intent_code_send';

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
				$p = $mod->getMfaController()
						 ->getProvidersForUser( $user, true )[ Email::SLUG ] ?? null;
				$success = !empty( $p ) && $p->sendEmailTwoFactorVerify( $plainNonce );
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $success ? __( 'One-Time Password was sent to your registered email address.', 'wp-simple-firewall' )
				: __( 'There was a problem sending the One-Time Password email.', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}