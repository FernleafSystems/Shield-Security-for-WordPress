<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;

abstract class Base {

	use ModConsumer;

	const HOOK_PRIORITY = 1000; // so only authenticated user is notified of account state.

	public function run() {
		add_filter( 'authenticate', [ $this, 'checkUser' ], static::HOOK_PRIORITY );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * it's own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $user
	 * @return \WP_User|\WP_Error
	 */
	public function checkUser( $user ) {
		if ( $user instanceof \WP_User ) {
			$user = $this->processUser( $user, $this->getCon()->getUserMeta( $user ) );
		}
		return $user;
	}

	/**
	 * Test the User and its Meta and if it fails return \WP_Error; Always return Error or User
	 * @param \WP_User       $user
	 * @param ShieldUserMeta $meta
	 * @return \WP_Error|\WP_User
	 */
	abstract protected function processUser( \WP_User $user, $meta );
}