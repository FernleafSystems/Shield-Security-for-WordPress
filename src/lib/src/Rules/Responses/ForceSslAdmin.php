<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class ForceSslAdmin extends Base {

	public const SLUG = 'force_ssl_admin';

	public function execResponse() :void {
		force_ssl_admin( true );
	}
}