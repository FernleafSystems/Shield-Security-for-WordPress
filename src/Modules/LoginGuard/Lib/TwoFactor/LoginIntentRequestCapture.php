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

	private string $loginNonce;

	public function runCapture() {
		$con = self::con();
		$req = Services::Request();

		try {
			$userID = LoginRequestValues::positiveUserId( $req->post( 'wp_user_id' ) );
			$loginNonce = LoginRequestValues::nonEmptyString( $req->post( 'login_nonce' ) );
			if ( $userID === null || $loginNonce === null ) {
				throw new NotValidUserException();
			}
			$user = Services::WpUsers()->getUserById( $userID );
			if ( !$user instanceof \WP_User ) {
				throw new NotValidUserException();
			}
			$this->user = $user;
			$this->loginNonce = $loginNonce;
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
			$redirect = LoginRequestValues::safeRedirect( $req->post( 'cancel_href' ), '' );
			$redirect === '' ? Services::Response()->redirectToLogin() : Services::Response()->redirect( $redirect );
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
					'render_slug' => $con->opts->optIs( 'mfa_verify_page', MfaController::LOGIN_INTENT_PAGE_FORMAT_SHIELD ) ? ShieldLoginIntentPage::SLUG : WpReplicaLoginIntentPage::SLUG,
					'render_data' => [
						'user_id'           => $this->user->ID,
						'include_body'      => true,
						'plain_login_nonce' => $this->loginNonce,
						'interim_login'     => LoginRequestValues::tokenValue( $req->request( 'interim-login', false, '' ), '1' ),
						'redirect_to'       => LoginRequestValues::safeRedirect( $req->request( 'redirect_to', false, '' ), $req->getPath() ),
						'rememberme'        => LoginRequestValues::tokenValue( $req->request( 'rememberme', false, '' ), 'forever' ),
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
			->run( $this->loginNonce, LoginRequestValues::isToken( $req->post( 'cancel' ), '1' ) );

		if ( $validatedSlug ) {
			wp_set_auth_cookie( $this->user->ID, LoginRequestValues::isToken( $req->post( 'rememberme' ), 'forever' ) );

			if ( LoginRequestValues::isToken( $req->post( 'skip_mfa' ), 'Y' ) ) {
				( new MfaSkip() )->addMfaSkip( $this->user );
			}

			$con->comps->events->fireEvent( '2fa_success' );

			global $interim_login;
			$interim_login = LoginRequestValues::isToken( $req->request( 'interim-login' ), '1' );
			if ( $interim_login ) {
				add_filter( 'login_message', '__return_empty_string', 100, 0 );

				$con->action_router->action( FullPageDisplayDynamic::class, [
					'render_slug' => WpReplicaLoginIntentPage::SLUG,
					'render_data' => [
						'user_id'           => $this->user->ID,
						'include_body'      => false,
						'interim_message'   => __( '2FA authentication verified successfully.', 'wp-simple-firewall' ),
						'plain_login_nonce' => $this->loginNonce,
						'interim_login'     => '1',
						'redirect_to'       => LoginRequestValues::safeRedirect( $req->request( 'redirect_to', false, '' ), $req->getPath() ),
						'rememberme'        => LoginRequestValues::tokenValue( $req->request( 'rememberme', false, '' ), 'forever' ),
					],
				] );
			}

			$fallback = $req->getPath();
			$redirect = LoginRequestValues::safeRedirect( $req->request( 'redirect_to', false, $fallback ), $fallback );
			Services::Response()->redirect(
				LoginRequestValues::safeRedirect(
					apply_filters( 'login_redirect', $redirect, $redirect, $this->user ),
					$fallback
				),
				[], true, false
			);
		}
	}
}
