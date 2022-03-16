<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Auth;

use FernleafSystems\Wordpress\Services\Services;
use MainWP\Child\MainWP_Connect;

/**
 * This reproduces the authentication done by the MainWP client in MainWP_Child::parse_init().
 */
class ReproduceClientAuthByKey {

	public static function Auth() :bool {
		$req = Services::Request();

		// 'function' for actions, 'where' for login
		$functionOrWhere = $req->request( 'function' );
		if ( empty( $functionOrWhere ) ) {
			$functionOrWhere = $req->request( 'where' );
		}

		return (bool)MainWP_Connect::instance()->auth(
			rawurldecode( (string)$req->request( 'mainwpsignature', '' ) ),
			sanitize_text_field( $functionOrWhere ),
			sanitize_text_field( $req->request( 'nonce' ) ),
			sanitize_text_field( $req->request( 'nossl' ) )
		);
	}
}