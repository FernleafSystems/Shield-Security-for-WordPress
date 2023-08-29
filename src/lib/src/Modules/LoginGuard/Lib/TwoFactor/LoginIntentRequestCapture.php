<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	Actions\FullPageDisplay\FullPageDisplayDynamic,
	Actions\Render\FullPage\Mfa\ShieldLoginIntentPage,
	Actions\Render\FullPage\Mfa\WpReplicaLoginIntentPage,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	InvalidLoginIntentException,
	LoginCancelException,
	NoActiveProvidersForUserException,
	NotValidUserException,
	OtpVerificationFailedException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentRequestCapture {

	use ExecOnce;
	use ModConsumer;

	/**
	 * @var \WP_User
	 */
	private $user;

	protected function canRun() :bool {
		return false;
	}

	public function runCapture() {
		$con = self::con();
		$req = Services::Request();

		try {
			$user = $req->post( 'wp_user_id' ) ? Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) ) : null;
			if ( empty( $user ) ) {
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
				$useShieldLoginIntentPage = $con->getModule_LoginGuard()->getMfaController()->useLoginIntentPage();
				$con->action_router->action( FullPageDisplayDynamic::class, [
					'render_slug' => $useShieldLoginIntentPage ? ShieldLoginIntentPage::SLUG : WpReplicaLoginIntentPage::SLUG,
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
	 * @throws Shield\ActionRouter\Exceptions\ActionDoesNotExistException
	 * @throws Shield\ActionRouter\Exceptions\ActionTypeDoesNotExistException
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
				add_filter( 'login_message', function () {
					return '';
				}, 100, 0 );

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
			else {
				$con->getAdminNotices()
					->addFlash(
						\implode( ' ', [
							__( 'Two-Factor Authentication Success!', 'wp-simple-firewall' ),
							__( 'Thank you for authenticating your login.', 'wp-simple-firewall' ),
							__( "To use a backup code again to login, you'll need to create it in your user profile.", 'wp-simple-firewall' )
						] ),
						$this->user
					);
			}

			$redirect = $req->request( 'redirect_to', false, $req->getPath() );
			Services::Response()->redirect(
				apply_filters( 'login_redirect', $redirect, $redirect, $this->user ),
				[], true, false
			);
		}
	}
}