<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class IsIpBlocked extends Base {

	const SLUG = 'is_ip_blocked';

	protected function execResponse() :bool {
		$this->getCon()->req->is_ip_blocked = true;
		return true;
	}
}