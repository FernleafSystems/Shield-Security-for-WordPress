<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class UserRotateAuthCookies extends Base {

	public function execResponse() :void {

		$loggedInCookie = Services::Request()->cookie( LOGGED_IN_COOKIE );
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
			$sessionCon = self::con()->getModule_Plugin()->getSessionCon();
			$userID = $sessionCon->current()->shield[ 'user_id' ] ?? 0;
			if ( $userID > 0 ) {
				wp_set_auth_cookie( $userID, false, '', $parsed[ 'token' ] );
				$sessionCon->updateSessionParameter( 'token_started_at', $this->req->carbon->timestamp );
			}
		}
	}
}