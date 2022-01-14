<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\WpLoginPageReplica;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class WPLoginPageHandler extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Utilities\Consumer\WpLoginCapture;

	private $loginNonce = '';

	private $redirectTo = '';

	private $errorMsg = '';

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ] );
		$this->setupLoginCaptureHooks();
	}

	public function onWpInit() {
		try {
			$this->capture2faVerify();
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			Services::Response()->redirectToLogin();
		}
	}

	/**
	 * @throws \Exception
	 */
	public function capture2faVerify() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		if ( $this->getCon()->getShieldAction() === 'wp_login_2fa_verify' ) {
			$user = Services::WpUsers()->getUserById( $req->post( 'wp_user_id' ) );
			if ( empty( $user ) ) {
				throw new \Exception( 'Not a valid user' );
			}

			$valid = ( new LoginGuard\Lib\TwoFactor\ValidateLoginIntentRequest() )
				->setMfaController( $mod->getMfaController() )
				->run( $user );

			if ( $valid ) {
				wp_set_auth_cookie( $user->ID, (bool)$req->post( 'rememberme' ) );

				global $interim_login;
				$interim_login = (bool)$req->request( 'interim-login' );
				if ( $interim_login ) {
					add_filter( 'login_message', function ( $message ) {
						return '';
					} );
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
		// TODO: Implement captureLogin() method.
	}

	/**
	 * @param string   $userLogin
	 * @param \WP_User $user
	 */
	public function onWpLogin( $userLogin, $user ) {
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