<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class UserSessionLogoutCurrent extends Base {

	public function execResponse() :void {
		Services::WpUsers()->logoutUser();
	}
}