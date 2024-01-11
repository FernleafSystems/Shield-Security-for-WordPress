<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class UserSessionLogoutAll extends Base {

	public function execResponse() :void {
		$id = Services::WpUsers()->getCurrentWpUserId();
		if ( $id > 0 && \class_exists( '\WP_Session_Tokens' ) ) {
			\WP_Session_Tokens::get_instance( $id )->destroy_all();
		}
	}
}