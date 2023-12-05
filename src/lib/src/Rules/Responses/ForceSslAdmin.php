<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class ForceSslAdmin extends Base {

	public const SLUG = 'force_ssl_admin';

	public function execResponse() :bool {
		if ( !\defined( 'FORCE_SSL_ADMIN' ) ) {
			\define( 'FORCE_SSL_ADMIN', true );
		}
		force_ssl_admin( true );
		return true;
	}
}