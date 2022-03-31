<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetSecurityAdmin extends Base {

	const SLUG = 'set_security_admin';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_security_admin = true;
		return true;
	}
}