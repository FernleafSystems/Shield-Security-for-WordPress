<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class SetSecurityAdmin extends Base {

	public const SLUG = 'set_security_admin';

	public function execResponse() :bool {
		self::con()->this_req->is_security_admin = true;
		return true;
	}
}