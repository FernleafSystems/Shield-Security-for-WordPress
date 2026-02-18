<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Services\Services;

/**
 * todo: duplicates logic in LoginIntentRequestValidate
 */
class MfaEmailAutoLogin extends BaseAction {

	use AuthNotRequired;

	public const SLUG = 'mfa_email_auto_login';

	protected function exec() {
		$con = self::con();
		$mfaCon = $con->comps->mfa;
		$userID = (int)$this->action_data[ 'user_id' ];

		$user = Services::WpUsers()->getUserById( $userID );
		if ( empty( $user ) ) {
			throw new ActionException( __( 'No such user', 'wp-simple-firewall' ) );
		}
		if ( !$mfaCon->verifyLoginNonce( $user, $this->action_data[ 'login_nonce' ] ) ) {
			// TODO: trigger offense?
			throw new ActionException( __( 'Invalid login nonce', 'wp-simple-firewall' ) );
		}
		$providers = $mfaCon->getProvidersActiveForUser( $user );
		if ( empty( $providers ) ) {
			throw new ActionException( __( 'No active providers for user.', 'wp-simple-firewall' ) );
		}
		$emailProvider = $providers[ Email::ProviderSlug() ] ?? null;
		if ( empty( $emailProvider ) ) {
			throw new ActionException( __( 'No email provider for user.', 'wp-simple-firewall' ) );
		}

		$success = false;
		try {
			\ob_start();
			if ( $emailProvider->validateLoginIntent( $mfaCon->findHashedNonce( $user, $this->action_data[ 'login_nonce' ] ) ) ) {
				$success = true;
				if ( \method_exists( $emailProvider, 'postSuccessActions' ) ) {
					$emailProvider->postSuccessActions();
				}
				wp_set_auth_cookie( $userID, true );
				$con->comps->events->fireEvent( '2fa_success' );
			}
		}
		catch ( \Exception $e ) {
			error_log( 'failed auto login:'.$e->getMessage() );
		}
		finally {
			$con->comps->events->fireEvent(
				$success ? '2fa_verify_success' : '2fa_verify_fail',
				[
					'audit_params' => [
						'user_login' => $userID,
						'method'     => $emailProvider->getProviderName(),
					]
				]
			);
			\ob_end_clean();
		}

		$this->response()->action_response_data = [
			'success' => $success,
		];

		$redirectTo = $this->action_data[ 'redirect_to' ] ?? '';
		$this->response()->next_step = [
			'type' => 'redirect',
			'url'  => empty( $redirectTo ) ? Services::WpGeneral()->getHomeUrl() : $redirectTo,
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'icwp_wpsf_email_otp',
			'login_nonce',
			'user_id',
		];
	}
}
