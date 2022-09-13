<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class LoginRequestCapture extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Utilities\Consumer\WpLoginCapture;

	protected function run() {
		$this->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$mfaCon = $mod->getMfaController();
		if ( $mfaCon->isSubjectToLoginIntent( $user ) && !Services::WpUsers()->isAppPasswordAuth() ) {

			if ( !$this->canUserMfaSkip( $user ) ) {

				foreach ( $mfaCon->getProvidersForUser( $user, true ) as $provider ) {
					$provider->setUser( $user )
							 ->captureLoginAttempt();
				}

				$loginNonce = bin2hex( random_bytes( 32 ) );
				$loginNonceHashed = wp_hash_password( $loginNonce.$user->ID );

				$intents = $mfaCon->getActiveLoginIntents( $user );
				$intents[ $loginNonceHashed ] = [
					'hash'     => $loginNonceHashed,
					'start'    => Services::Request()->ts(),
					'attempts' => 0,
				];

				$this->getCon()->getUserMeta( $user )->login_intents = $intents;

				$loggedInCookie = $this->getLoggedInCookie();
				if ( !empty( $loggedInCookie ) ) {
					$parsed = \wp_parse_auth_cookie( $loggedInCookie );
					if ( !empty( $parsed[ 'token' ] ) ) {
						\WP_Session_Tokens::get_instance( $user->ID )->destroy( $parsed[ 'token' ] );
					}
				}

				Services::WpUsers()->logoutUser( true );

				$req = Services::Request();
				$pageRender = $mfaCon->useLoginIntentPage() ? new Render\RenderLoginIntentPage() : new Render\RenderWpLoginReplica();
				$pageRender->setMod( $mod )
						   ->setWpUser( $user );
				$pageRender->login_nonce = $loginNonce;
				$pageRender->interim_login = $req->request( 'interim-login' );
				$pageRender->redirect_to = $req->request( 'redirect_to', false, '' );
				$pageRender->rememberme = $req->request( 'rememberme' );
				$pageRender->render(); // die();
			}
		}
	}

	private function canUserMfaSkip( \WP_User $user ) :bool {
		$canSkip = ( new MfaSkip() )
			->setMod( $this->getMod() )
			->canMfaSkip( $user );
		return (bool)apply_filters( 'shield/2fa_skip', apply_filters( 'odp-shield-2fa_skip', $canSkip ) );
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
}