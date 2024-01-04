<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class UserUnsuspend extends Base {

	public function execResponse() :void {
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User ) {
			self::con()
				->getModule_UserManagement()
				->getUserSuspendCon()
				->addRemoveHardSuspendUser( $user, false );
		}
	}
}