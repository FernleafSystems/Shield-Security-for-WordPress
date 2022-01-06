<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 13.0.5
 */
class UserProfile {

	use MfaControllerConsumer;
	use ExecOnce;

	protected function run() {
	}

	/**
	 * This MUST only ever be hooked into when the User is looking at their OWN profile, so we can use "current user"
	 * functions.  Otherwise we need to be careful of mixing up users.
	 * @param \WP_User $user
	 */
	public function addOptionsToUserProfile( $user ) {
	}

	/**
	 * ONLY TO BE HOOKED TO USER PROFILE EDIT
	 * @param \WP_User $user
	 */
	public function addOptionsToUserEditProfile( $user ) {
	}
}