<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class SetIsTrustedBot extends Base {

	public const SLUG = 'set_is_trusted_bot';

	public function execResponse() :bool {
		self::con()->this_req->is_trusted_bot = true;
		return true;
	}
}