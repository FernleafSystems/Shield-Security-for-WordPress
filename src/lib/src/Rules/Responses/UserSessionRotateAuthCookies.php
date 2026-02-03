<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\SessionVO;

/**
 * Rotates an existing user session by:
 * - clearing out the old session (remove cookies + delete backed-session)
 * - generating a new session and sending the cookies. (forcing expiration to that of the deleted session)
 * - copy over any Shield-specific items.
 */
class UserSessionRotateAuthCookies extends Base {

	public function execResponse() :void {
		did_action( 'init' ) ? $this->runRotate() : add_action( 'init', fn() => $this->runRotate() );
	}

	private function runRotate() :void {
		if ( $this->req->session instanceof SessionVO && $this->req->session->valid ) {

			$loggedInCookie = $this->req->request->cookie_copy[ LOGGED_IN_COOKIE ];
			if ( !empty( $loggedInCookie ) ) {
				$parsed = wp_parse_auth_cookie( $loggedInCookie );
			}
			if ( empty( $parsed ) ) {
				foreach ( [ 'logged_in', 'secure_auth', 'auth' ] as $type ) {
					$parsed = wp_parse_auth_cookie( '', $type );
					if ( !empty( $parsed ) ) {
						break;
					}
				}
			}

			if ( !empty( $parsed[ 'token' ] ) ) {
				$sessionCon = self::con()->comps->session;
				$current = $sessionCon->current();
				$userID = $current->shield[ 'user_id' ] ?? 0;

				if ( $userID > 0 ) {

					$sessionTokensManager = \WP_Session_Tokens::get_instance( $userID );

					// remove existing session
					wp_clear_auth_cookie();
					$sessionTokensManager->destroy( $parsed[ 'token' ] );

					// create our all-new session token
					$newToken = $sessionTokensManager->create( $current->shield[ 'expires_at' ] );

					// send new session cookies
					$expirationDelta = $current->shield[ 'expires_at' ] - $this->req->carbon->timestamp;
					add_filter( 'auth_cookie_expiration', $cl = fn() => $expirationDelta, \PHP_INT_MAX, 0 );
					wp_set_auth_cookie( $userID, false, '', $newToken );
					remove_filter( 'auth_cookie_expiration', $cl, \PHP_INT_MAX );

					try { // copy-over some Shield session parameters
						$newSession = $sessionCon->buildSession( $userID, $newToken );
						foreach ( [ 'session_started_at', 'secadmin_at' ] as $key ) {
							if ( $current->shield[ $key ] ?? false ) {
								$sessionCon->updateSessionParameter( $newSession, $key, $current->shield[ $key ] );
							}
						}
						$this->req->session = $newSession;
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}
	}
}