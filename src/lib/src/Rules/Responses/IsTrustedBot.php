<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class IsTrustedBot extends Base {

	const SLUG = 'is_trusted_bot';

	protected function execResponse() :bool {
		$this->getCon()->req->is_trusted_bot = true;
		return true;
	}
}