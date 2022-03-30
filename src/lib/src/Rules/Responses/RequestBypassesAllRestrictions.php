<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class RequestBypassesAllRestrictions extends Base {

	const SLUG = 'request_bypasses_all_restrictions';

	protected function execResponse() :bool {
		$this->getCon()->this_req->request_bypasses_all_restrictions = true;
		return true;
	}
}