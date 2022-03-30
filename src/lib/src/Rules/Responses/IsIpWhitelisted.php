<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class IsIpWhitelisted extends Base {

	const SLUG = 'is_ip_whitelisted';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_ip_whitelisted = true;
		return true;
	}
}