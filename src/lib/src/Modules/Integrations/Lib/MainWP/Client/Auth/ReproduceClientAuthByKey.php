<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Auth;

use FernleafSystems\Wordpress\Services\Services;
use MainWP\Child\MainWP_Connect;

/**
 * This reproduces the authentication done by the MainWP client in MainWP_Child::parse_init().
 *
 * Class ReproduceClientAuthByKey
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Auth
 */
class ReproduceClientAuthByKey {

	public static function Auth() :bool {
		$req = Services::Request();
		return (bool)MainWP_Connect::instance()->auth(
			rawurldecode( $req->post( 'mainwpsignature' ) ),
			sanitize_text_field( $req->post( 'function' ) ),
			sanitize_text_field( $req->post( 'nonce' ) ),
			sanitize_text_field( $req->post( 'nonce' ) )
		);
	}
}