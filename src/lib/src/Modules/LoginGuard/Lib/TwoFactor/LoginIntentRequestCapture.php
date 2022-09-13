<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	LoginCancelException,
	NoActiveProvidersForUserException,
	InvalidLoginIntentException,
	NotValidUserException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentRequestCapture extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	/**
	 * @var \WP_User
	 */
	private $user;

	protected function canRun() :bool {
		return Services::Request()->isPost()
			   && $this->getCon()->getShieldAction() === 'wp_login_2fa_verify'
			   && !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ], 8 ); // before rename login render
	}

	public function onWpLoaded() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
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
			$this->getCon()->getUserMeta( $this->user )->login_intents = [];
			$redirect = $req->post( 'cancel_href' );
			empty( $redirect ) ? Services::Response()->redirectToLogin() : Services::Response()->redirect( $redirect );
		}
		catch ( TooManyAttemptsException $e ) {
			$this->getCon()->getUserMeta( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'too_many_attempts'
			] );
		}
		catch ( NoActiveProvidersForUserException $e ) {
			$this->getCon()->getUserMeta( $this->user )->login_intents = [];
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_providers'
			] );
		}
		catch ( CouldNotValidate2FA $e ) {
			// Allow a further attempt to 2FA
			$pageRender = $mfaCon->useLoginIntentPage() ? new Render\RenderLoginIntentPage() : new Render\RenderWpLoginReplica();
			$pageRender->setMod( $mod )
					   ->setWpUser( $this->user );
			$pageRender->plain_login_nonce = $req->request( 'login_nonce', false, '' );
			$pageRender->redirect_to = $req->request( 'redirect_to', false, '' );
			$pageRender->rememberme = $req->request( 'rememberme' );
			$pageRender->msg_error = __( 'Could not verify your 2FA codes', 'wp-simple-firewall' );
			$pageRender->render(); // die();
		}
	}

	/**
	 * @throws CouldNotValidate2FA
	 * @throws Exceptions\TooManyAttemptsException
	 * @throws LoginCancelException
	 * @throws NoActiveProvidersForUserException
	 * @throws InvalidLoginIntentException
	 */
	private function capture() {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$valid = ( new LoginIntentRequestValidate() )
			->setMod( $mod )
			->setWpUser( $this->user )
			->run( (string)$req->post( 'login_nonce' ) );

		if ( $req->post( 'cancel' ) ) {
			throw new LoginCancelException();
		}

		if ( $valid ) {
			wp_set_auth_cookie( $this->user->ID, (bool)$req->post( 'rememberme' ) );

			if ( $req->post( 'skip_mfa' ) === 'Y' ) {
				( new MfaSkip() )
					->setMod( $this->getMod() )
					->addMfaSkip( $this->user );
			}

			$con->fireEvent( '2fa_success' );

			global $interim_login;
			$interim_login = (bool)$req->request( 'interim-login' );
			if ( $interim_login ) {
				add_filter( 'login_message', function () {
					return '';
				}, 100, 0 );
				$renderer = ( new Render\RenderWpLoginReplica() )
					->setMod( $mod )
					->setWpUser( $this->user );
				$renderer->interim_message = __( '2FA authentication verified successfully.', 'wp-simple-firewall' );
				$renderer->include_body = false;
				$renderer->render();
			}
			else {
				$flash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $opts->isEnabledBackupCodes() ) {
					$flash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' );
				}
				$this->getMod()->setFlashAdminNotice( $flash, $this->user );
			}

			$redirect = $req->request( 'redirect_to', false, $req->getPath() );
			Services::Response()->redirect(
				apply_filters( 'login_redirect', $redirect, $redirect, $this->user ),
				[], true, false
			);
		}
	}
}