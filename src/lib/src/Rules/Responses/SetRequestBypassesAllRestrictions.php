<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetRequestBypassesAllRestrictions extends Base {

	public const SLUG = 'set_request_bypasses_all_restrictions';

	protected function execResponse() :bool {
		self::con()->this_req->request_bypasses_all_restrictions = true;
		return true;
	}
}