<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\WpLoginPageReplica;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Exceptions\{
	CouldNotValidate2FA,
	NoActiveProvidersForUserException,
	NoLoginIntentForUserException,
	NotValidUserException
};
use FernleafSystems\Wordpress\Services\Services;

class WPLoginPageHandler extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Utilities\Consumer\WpLoginCapture;

	private $loginNonce = '';

	private $redirectTo = '';

	private $errorMsg = '';

	protected function run() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		$this->setupLoginCaptureHooks();
	}

	public function onWpLoaded() {
		try {
			$this->capture2faVerify();
		}
		catch ( NotValidUserException $e ) {
			// put error about no login intent
			$this->getCon()->getAdminNotices()->addFlash( 'No user login intent found', true, true );
			Services::Response()->redirectToLogin();
		}
		catch ( NoLoginIntentForUserException $e ) {
			// put error about no login intent
			$this->getCon()->getAdminNotices()->addFlash( 'No user login intent found', true, true );
			Services::Response()->redirectToLogin();
		}
		catch ( CouldNotValidate2FA $e ) {
			// Allow a further attempt to login
			$req = Services::Request();
			echo ( new RenderWpLoginReplica(
				Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) ),
				(string)$req->post( 'wp_user_id' ),
				(string)$req->post( 'rememberme' ),
				'Could not verify your 2FA codes'
			) )->setMod( $this->getMod() )
			   ->render();
			die();
		}
		catch ( NoActiveProvidersForUserException $e ) {
			// put error about no 2FA providers
			Services::Response()->redirectToLogin();
		}
	}

	/**
	 * @throws CouldNotValidate2FA
	 * @throws NoActiveProvidersForUserException
	 * @throws NoLoginIntentForUserException
	 * @throws NotValidUserException
	 */
	public function capture2faVerify() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		if ( $this->getCon()->getShieldAction() === 'wp_login_2fa_verify' ) {

			$user = Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) );
			if ( empty( $user ) ) {
				throw new NotValidUserException();
			}

			$valid = ( new LoginGuard\Lib\TwoFactor\ValidateLoginIntentRequest() )
				->setMfaController( $mod->getMfaController() )
				->run( $user );

			if ( $valid ) {
				wp_set_auth_cookie( $user->ID, (bool)$req->post( 'rememberme' ) );

				global $interim_login;
				$interim_login = (bool)$req->request( 'interim-login' );
				if ( $interim_login ) {
					add_filter( 'login_message', function () {
						return '';
					}, 100, 0 );
					echo ( new RenderWpLoginReplica( $user ) )
						->setMod( $mod )
						->setInterimMessage( __( '2FA authentication verified successfully.', 'wp-simple-firewall' ) )
						->render( false );
					die();
				}

				Services::Response()->redirect(
					apply_filters( 'login_redirect', $req->post( 'redirect_to', '/' ), $user ),
					[], true, false
				);
			}
		}
	}

	/**
	 * We override the trait as we don't want to process the 2fa login on the cookie setting
	 * just the wp_login action. But we DO need to capture the cookie being set here.
	 *
	 * @param string $cookie
	 * @param int    $expire
	 * @param int    $expiration
	 * @param int    $userID
	 */
	public function onWpSetLoggedInCookie( $cookie, $expire, $expiration, $userID ) {
		if ( is_string( $cookie ) ) {
			$this->setLoggedInCookie( $cookie );
		}
	}

	protected function getHookPriority() :int {
		return 15;
	}

	protected function captureLogin( \WP_User $user ) {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		if ( $mfaCon->isSubjectToLoginIntent( $user ) && !Services::WpUsers()->isAppPasswordAuth() ) {

			if ( !$mfaCon->canUserMfaSkip( $user ) ) {
				$loggedInCookie = $this->getLoggedInCookie();
				if ( !empty( $loggedInCookie ) ) {
					$parsed = \wp_parse_auth_cookie( $loggedInCookie );
					if ( !empty( $parsed[ 'token' ] ) ) {
						\WP_Session_Tokens::get_instance( $user->ID )->destroy( $parsed[ 'token' ] );
					}
				}

				\wp_clear_auth_cookie();

				//TODO: nonce
				$req = Services::Request();
				echo ( new RenderWpLoginReplica(
					$user, (string)$req->request( 'redirect_to' ), (string)$req->request( 'rememberme' )
				) )->setMod( $mod )
				   ->render();
				die();
			}
		}
	}
}