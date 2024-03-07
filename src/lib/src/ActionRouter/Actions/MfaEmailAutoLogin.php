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

		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );
		if ( empty( $user ) ) {
			throw new ActionException( 'No such user' );
		}
		if ( !$mfaCon->verifyLoginNonce( $user, $this->action_data[ 'login_nonce' ] ) ) {
			// TODO: trigger offense?
			throw new ActionException( 'invalid login nonce' );
		}
		$providers = $mfaCon->getProvidersActiveForUser( $user );
		if ( empty( $providers ) ) {
			throw new ActionException( 'no active providers for user.' );
		}
		$emailProvider = $providers[ Email::ProviderSlug() ] ?? null;
		if ( empty( $emailProvider ) ) {
			throw new ActionException( 'no email provider for user.' );
		}

		$success = false;
		try {
			\ob_start();
			if ( $emailProvider->validateLoginIntent( $mfaCon->findHashedNonce( $user, $this->action_data[ 'login_nonce' ] ) ) ) {
				$success = true;
				$emailProvider->postSuccessActions();
				wp_set_auth_cookie( $this->action_data[ 'user_id' ], true );
				$con->fireEvent( '2fa_success' );
			}
		}
		catch ( \Exception $e ) {
			error_log( 'failed auto login:'.$e->getMessage() );
		}
		finally {
			$con->fireEvent(
				$success ? '2fa_verify_success' : '2fa_verify_fail',
				[
					'audit_params' => [
						'user_login' => $this->action_data[ 'user_id' ],
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
		if ( !empty( $redirectTo ) ) {
			$redirectTo = \base64_decode( $redirectTo );
		}
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