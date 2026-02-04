<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class UserSuspend extends Base {

	public function execResponse() :void {
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User ) {
			self::con()->comps->user_suspend->addRemoveHardSuspendUser( $user );
		}
	}
}