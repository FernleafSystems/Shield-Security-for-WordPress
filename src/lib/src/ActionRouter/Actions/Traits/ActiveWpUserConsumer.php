<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

use FernleafSystems\Wordpress\Services\Services;

trait ActiveWpUserConsumer {

	public function getActiveWPUser() :?\WP_User {
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'active_wp_user' ] ?? null );
		return $user instanceof \WP_User ? $user : Services::WpUsers()->getCurrentWpUser();
	}

	public function hasActiveWPUser() :bool {
		return $this->getActiveWPUser() instanceof \WP_User;
	}
}