<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Trait for actions that operate on the current authenticated user's profile.
 *
 * SECURITY: This trait always returns the current logged-in user.
 * For login flow actions (unauthenticated), use LoginWpUserConsumer instead.
 */
trait ActiveWpUserConsumer {

	public function getActiveWPUser() :?\WP_User {
		$user = Services::WpUsers()->getCurrentWpUser();
		return $user instanceof \WP_User ? $user : null;
	}

	public function hasActiveWPUser() :bool {
		return $this->getActiveWPUser() instanceof \WP_User;
	}
}