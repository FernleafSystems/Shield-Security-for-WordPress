<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class SetIsTrustedBot extends Base {

	public const SLUG = 'set_is_trusted_bot';

	protected function execResponse() :bool {
		$this->getCon()->this_req->is_trusted_bot = true;
		return true;
	}
}