<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\StandardFullPageDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\{
	ShieldLoginIntentPage,
	WpReplicaLoginIntentPage
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class LoginRequestCapture {

	use ExecOnce;
	use LoginGuard\ModConsumer;
	use Shield\Utilities\Consumer\WpLoginCapture;

	protected function run() {
		$this->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		$con = $this->con();
		$mfaCon = $this->mod()->getMfaController();
		if ( $mfaCon->isSubjectToLoginIntent( $user ) && !Services::WpUsers()->isAppPasswordAuth() ) {

			if ( !$this->canUserMfaSkip( $user ) ) {
				$opts = $this->opts();

				$loginNonce = bin2hex( random_bytes( 32 ) );
				$loginNonceHashed = wp_hash_password( $loginNonce.$user->ID );

				$intents = $mfaCon->getActiveLoginIntents( $user );
				$intents[ $loginNonceHashed ] = [
					'hash'     => $loginNonceHashed,
					'start'    => Services::Request()->ts(),
					'attempts' => 0,
				];

				$con->user_metas->for( $user )->login_intents = $intents;

				$loggedInCookie = $this->getLoggedInCookie();
				if ( !empty( $loggedInCookie ) ) {
					$parsed = \wp_parse_auth_cookie( $loggedInCookie );
					if ( !empty( $parsed[ 'token' ] ) ) {
						\WP_Session_Tokens::get_instance( $user->ID )->destroy( $parsed[ 'token' ] );
					}
				}

				Services::WpUsers()->logoutUser( true );
				$req = Services::Request();
				try {
					$con->action_router->action( StandardFullPageDisplay::class, [
						'render_slug' => ( $opts->getMfaLoginIntentFormat() === $mfaCon::LOGIN_INTENT_PAGE_FORMAT_SHIELD ) ?
							ShieldLoginIntentPage::SLUG : WpReplicaLoginIntentPage::SLUG,
						'render_data' => [
							'user_id'           => $user->ID,
							'include_body'      => true,
							'plain_login_nonce' => $loginNonce,
							'interim_login'     => $req->request( 'interim-login', false, '' ),
							'redirect_to'       => $req->request( 'redirect_to', false, '' ),
							'rememberme'        => $req->request( 'rememberme', false, '' ),
							'msg_error'         => '',
						],
					] );
				}
				catch ( ActionException $e ) {
					die( $e->getMessage() );
				}
			}
		}
	}

	private function canUserMfaSkip( \WP_User $user ) :bool {
		return (bool)apply_filters( 'shield/2fa_skip',
			apply_filters( 'odp-shield-2fa_skip', ( new MfaSkip() )->canMfaSkip( $user ) )
		);
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
		if ( \is_string( $cookie ) ) {
			$this->setLoggedInCookie( $cookie );
		}
	}

	protected function getHookPriority() :int {
		return 15;
	}
}