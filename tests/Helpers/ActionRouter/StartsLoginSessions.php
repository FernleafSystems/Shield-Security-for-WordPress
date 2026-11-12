<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

/**
 * A test user is normally set as the current user with no login session behind it. A test of what depends on
 * the login session starts one here, and puts the login cookie back when it's done.
 */
trait StartsLoginSessions {

	private bool $hadLoginCookie = false;

	private string $originalLoginCookie = '';

	/**
	 * Call before starting a session, so restoreLoginCookie() can put things back.
	 */
	protected function rememberLoginCookie() :void {
		$this->hadLoginCookie = isset( $_COOKIE[ \LOGGED_IN_COOKIE ] );
		$this->originalLoginCookie = $this->hadLoginCookie ? (string)$_COOKIE[ \LOGGED_IN_COOKIE ] : '';
	}

	protected function restoreLoginCookie() :void {
		if ( $this->hadLoginCookie ) {
			$_COOKIE[ \LOGGED_IN_COOKIE ] = $this->originalLoginCookie;
		}
		else {
			unset( $_COOKIE[ \LOGGED_IN_COOKIE ] );
		}
	}

	/**
	 * Starts a login session for the user and presents its cookie, as the browser would on its next request.
	 * @return string the session token
	 */
	protected function startLoginSession( int $userId ) :string {
		$expiration = \time() + \DAY_IN_SECONDS;
		$token = \WP_Session_Tokens::get_instance( $userId )->create( $expiration );
		$_COOKIE[ \LOGGED_IN_COOKIE ] = \wp_generate_auth_cookie( $userId, $expiration, 'logged_in', $token );
		return $token;
	}
}
