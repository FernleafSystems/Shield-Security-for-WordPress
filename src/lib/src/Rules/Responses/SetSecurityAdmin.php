<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetSecurityAdmin extends Base {

	public const SLUG = 'set_security_admin';

	protected function execResponse() :bool {
		$this->con()->this_req->is_security_admin = true;
		return true;
	}
}