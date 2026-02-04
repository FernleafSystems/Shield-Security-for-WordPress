<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\FullPageDisplay\FullPageDisplayDynamic,
	Actions\Render\FullPage\Mfa\ShieldLoginIntentPage,
	Actions\Render\FullPage\Mfa\WpReplicaLoginIntentPage,
	Exceptions\ActionDoesNotExistException,
	Exceptions\ActionException,
	Exceptions\ActionTypeDoesNotExistException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	InvalidLoginIntentException,
	LoginCancelException,
	NoActiveProvidersForUserException,
	NotValidUserException,
	OtpVerificationFailedException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentRequestCapture {

	use PluginControllerConsumer;

	private \WP_User $user;

	public function runCapture() {
		$con = self::con();
		$req = Services::Request();

		try {
			$user = $req->post( 'wp_user_id' ) ? Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) ) : null;
			if ( !$user instanceof \WP_User ) {
				throw new NotValidUserException();
			}
			$this->user = $user;
			$this->capture();
		}
		catch ( NotValidUserException $e ) {
			// output error about no login intent so there's no way to discern externally whether such a user exists
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( InvalidLoginIntentException $e ) {
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( LoginCancelException $e ) {
			// This should always be a user since we can only throw this exception after loading the user
			$con->user_metas->for( $this->user )->login_intents = [];
			$redirect = $req->post( 'cancel_href' );
			empty( $redirect ) ? Services::Response()->redirectToLogin() : Services::Response()->redirect( $redirect );
		}
		catch ( TooManyAttemptsException $e ) {
			$con->user_metas->for( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'too_many_attempts'
			] );
		}
		catch ( NoActiveProvidersForUserException $e ) {
			$con->user_metas->for( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_providers'
			] );
		}
		catch ( OtpVerificationFailedException|CouldNotValidate2FA $e ) {
			// Allow a further attempt to 2FA
			try {
				$con->action_router->action( FullPageDisplayDynamic::class, [
					'render_slug' => $con->opts->optIs( 'mfa_verify_page', 'custom_shield' ) ? ShieldLoginIntentPage::SLUG : WpReplicaLoginIntentPage::SLUG,
					'render_data' => [
						'user_id'           => $this->user->ID,
						'include_body'      => true,
						'plain_login_nonce' => $req->request( 'login_nonce', false, '' ),
						'interim_login'     => $req->request( 'interim-login', false, '' ),
						'redirect_to'       => $req->request( 'redirect_to', false, '' ),
						'rememberme'        => $req->request( 'rememberme', false, '' ),
						'msg_error'         => __( 'Could not verify your 2FA codes', 'wp-simple-firewall' ),
					],
				] );
			}
			catch ( ActionException $e ) {
				die( $e->getMessage() );
			}
		}
		catch ( ActionException $e ) {
		}
	}

	/**
	 * @throws ActionException
	 * @throws CouldNotValidate2FA
	 * @throws InvalidLoginIntentException
	 * @throws LoginCancelException
	 * @throws NoActiveProvidersForUserException
	 * @throws OtpVerificationFailedException
	 * @throws ActionDoesNotExistException
	 * @throws ActionTypeDoesNotExistException
	 * @throws TooManyAttemptsException
	 */
	private function capture() {
		$con = self::con();
		$req = Services::Request();

		$validatedSlug = ( new LoginIntentRequestValidate() )
			->setWpUser( $this->user )
			->run( (string)$req->post( 'login_nonce' ), $req->post( 'cancel' ) == '1' );

		if ( $validatedSlug ) {
			wp_set_auth_cookie( $this->user->ID, (bool)$req->post( 'rememberme' ) );

			if ( $req->post( 'skip_mfa' ) === 'Y' ) {
				( new MfaSkip() )->addMfaSkip( $this->user );
			}

			$con->fireEvent( '2fa_success' );

			global $interim_login;
			$interim_login = (bool)$req->request( 'interim-login' );
			if ( $interim_login ) {
				add_filter( 'login_message', '__return_empty_string', 100, 0 );

				$con->action_router->action( FullPageDisplayDynamic::class, [
					'render_slug' => WpReplicaLoginIntentPage::SLUG,
					'render_data' => [
						'user_id'           => $this->user->ID,
						'include_body'      => false,
						'interim_message'   => __( '2FA authentication verified successfully.', 'wp-simple-firewall' ),
						'plain_login_nonce' => $req->request( 'login_nonce', false, '' ),
						'interim_login'     => $req->request( 'interim-login', false, '' ),
						'redirect_to'       => $req->request( 'redirect_to', false, '' ),
						'rememberme'        => $req->request( 'rememberme', false, '' ),
					],
				] );
			}

			$redirect = $req->request( 'redirect_to', false, $req->getPath() );
			Services::Response()->redirect(
				apply_filters( 'login_redirect', $redirect, $redirect, $this->user ),
				[], true, false
			);
		}
	}
}