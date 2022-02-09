<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	LoginCancelException,
	NoActiveProvidersForUserException,
	NoLoginIntentForUserException,
	NotValidUserException,
	TooManyAttemptsException
};
use FernleafSystems\Wordpress\Services\Services;

class LoginIntentRequestCapture extends Shield\Modules\Base\Common\ExecOnceModConsumer {

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

		$user = $req->post( 'wp_user_id' ) ? Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) ) : null;

		try {
			$this->capture();
		}
		catch ( LoginCancelException $e ) {
			$redirect = $req->post( 'cancel_href' );
			empty( $redirect ) ? Services::Response()->redirectToLogin() : Services::Response()->redirect( $redirect );
		}
		catch ( NotValidUserException $e ) {
			// put error about no login intent
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( NoLoginIntentForUserException $e ) {
			// put error about no login intent
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_user_login_intent'
			] );
		}
		catch ( TooManyAttemptsException $e ) {
			// put error about no login intent
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'too_many_attempts'
			] );
		}
		catch ( NoActiveProvidersForUserException $e ) {
			Services::Response()->redirectToLogin( [
				'shield_msg' => 'no_providers'
			] );
		}
		catch ( CouldNotValidate2FA $e ) {
			// Allow a further attempt to 2FA
			$pageRender = $mfaCon->useLoginIntentPage() ? new Render\RenderLoginIntentPage() : new Render\RenderWpLoginReplica();
			$pageRender->setMod( $mod )
					   ->setWpUser( $user );
			$pageRender->login_nonce = $req->request( 'login_nonce', false, '' );
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
	 * @throws NoLoginIntentForUserException
	 * @throws NotValidUserException
	 */
	private function capture() {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		if ( $req->post( 'cancel' ) ) {
			throw new LoginCancelException();
		}

		$user = Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) );
		if ( empty( $user ) ) {
			throw new NotValidUserException();
		}

		$nonce = (string)$req->post( 'login_nonce' );
		if ( !preg_match( '#^[a-z0-9]{10}$#i', $nonce ) ) {
			throw new NoLoginIntentForUserException();
		}

		$valid = ( new LoginIntentRequestValidate() )
			->setMod( $mod )
			->setWpUser( $user )
			->run( $nonce );

		if ( $valid ) {
			wp_set_auth_cookie( $user->ID, (bool)$req->post( 'rememberme' ) );

			if ( $req->post( 'skip_mfa' ) === 'Y' ) {
				( new MfaSkip() )
					->setMod( $this->getMod() )
					->addMfaSkip( $user );
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
					->setWpUser( $user );
				$renderer->interim_message = __( '2FA authentication verified successfully.', 'wp-simple-firewall' );
				$renderer->include_body = false;
				$renderer->render();
			}
			else {
				$flash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $opts->isEnabledBackupCodes() ) {
					$flash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' );
				}
				$this->getMod()->setFlashAdminNotice( $flash, $user );
			}

			$redirect = $req->request( 'redirect_to', false, $req->getPath() );
			Services::Response()->redirect(
				apply_filters( 'login_redirect', $redirect, $redirect, $user ),
				[], true, false
			);
		}
	}
}