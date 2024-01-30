<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class SetIsTrustedBot extends Base {

	public const SLUG = 'set_is_trusted_bot';

	public function execResponse() :void {
		$this->req->is_trusted_request = true;
	}
}