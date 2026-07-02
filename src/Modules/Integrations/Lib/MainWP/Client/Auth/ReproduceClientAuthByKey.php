<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Auth;

use FernleafSystems\Wordpress\Services\Services;
use MainWP\Child\MainWP_Connect;

/**
 * This reproduces the authentication done by the MainWP client in MainWP_Child::parse_init().
 */
class ReproduceClientAuthByKey {

	public static function Auth() :bool {
		try {
			$req = Services::Request();

			// 'function' for actions, 'where' for login.
			$functionOrWhere = $req->request( 'function' );
			if ( empty( $functionOrWhere ) ) {
				$functionOrWhere = $req->request( 'where' );
			}

			$signature = $req->request( 'mainwpsignature', false, '' );
			$nonce = $req->request( 'nonce' );
			$nossl = $req->request( 'nossl' );

			return (bool)MainWP_Connect::instance()->auth(
				rawurldecode( \is_scalar( $signature ) ? (string)$signature : '' ),
				sanitize_text_field( \is_scalar( $functionOrWhere ) ? (string)$functionOrWhere : '' ),
				sanitize_text_field( \is_scalar( $nonce ) ? (string)$nonce : '' ),
				sanitize_text_field( \is_scalar( $nossl ) ? (string)$nossl : '' )
			);
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}
}
