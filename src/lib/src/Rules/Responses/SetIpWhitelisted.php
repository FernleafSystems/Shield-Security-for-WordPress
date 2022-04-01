<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetIpWhitelisted extends Base {

	const SLUG = 'set_ip_whitelisted';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_ip_whitelisted = true;
		return true;
	}
}