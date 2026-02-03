<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class UserClearAuthCookies extends Base {

	public function execResponse() :void {
		wp_clear_auth_cookie();
	}
}