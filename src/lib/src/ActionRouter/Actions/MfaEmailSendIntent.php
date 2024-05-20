<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Services\Services;

class MfaEmailSendIntent extends MfaUserConfigBase {

	use Traits\AuthNotRequired;

	public const SLUG = 'mfa_email_intent_code_send';

	protected function exec() {
		$success = false;
		$msg = __( 'There was a problem sending the One-Time Password email.', 'wp-simple-firewall' );
		$userID = $this->action_data[ 'wp_user_id' ];
		$plainNonce = $this->action_data[ 'login_nonce' ];
		if ( !empty( $userID ) && !empty( $plainNonce ) ) {
			$user = Services::WpUsers()->getUserById( $userID );
			if ( $user instanceof \WP_User ) {
				/** @var Email $p */
				$p = self::con()->comps->mfa->getProvidersActiveForUser( $user )[ Email::ProviderSlug() ] ?? null;
				try {
					if ( !empty( $p ) && $p->sendEmailTwoFactorVerify( $plainNonce, $this->action_data[ 'redirect_to' ] ?? '' ) ) {
						$success = true;
						$msg = \implode( " \n", [
							__( 'A new One-Time Password was sent to your email address.', 'wp-simple-firewall' ),
							sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
								__( 'Previously created One-Time Passwords are invalid.', 'wp-simple-firewall' ) )
						] );
					}
				}
				catch ( \Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => empty( $msg ) ? __( 'There was a problem sending the One-Time Password email.', 'wp-simple-firewall' ) : $msg,
			'page_reload' => false
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'login_nonce',
			'wp_user_id',
		];
	}
}