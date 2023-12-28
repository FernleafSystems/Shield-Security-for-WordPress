<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class SetRequestBypassesAllRestrictions extends Base {

	public const SLUG = 'set_request_bypasses_all_restrictions';

	public function execResponse() :void {
		$this->req->request_bypasses_all_restrictions = true;
	}
}