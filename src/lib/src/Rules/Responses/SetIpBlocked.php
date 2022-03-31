<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetIpBlocked extends Base {

	const SLUG = 'set_ip_blocked';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_ip_blocked = true;
		return true;
	}
}