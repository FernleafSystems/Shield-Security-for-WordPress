<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base {

	use ExecOnce;
	use PluginControllerConsumer;

	public const HOOK_PRIORITY = 1000; // so only authenticated user is notified of account state.

	protected function run() {
		add_filter( 'authenticate', [ $this, 'checkUser' ], static::HOOK_PRIORITY );
	}

	/**
	 * Should be a filter added to WordPress's "authenticate" filter, but before WordPress performs
	 * its own authentication (theirs is priority 30, so we could go in at around 20).
	 * @param null|\WP_User|\WP_Error $user
	 * @return \WP_User|\WP_Error
	 */
	public function checkUser( $user ) {
		if ( $user instanceof \WP_User ) {
			$meta = self::con()->user_metas->for( $user );
			if ( !empty( $meta ) ) {
				$user = $this->processUser( $user, $meta );
			}
		}
		return $user;
	}

	/**
	 * Test the User and its Meta and if it fails return \WP_Error; Always return Error or User
	 * @return \WP_Error|\WP_User
	 */
	abstract protected function processUser( \WP_User $user, ShieldUserMeta $meta );

	protected function getResetPasswordURL( string $context ) :string {
		return apply_filters( 'shield/user_suspend_reset_password_url',
			Services::WpGeneral()->getLostPasswordUrl(), $context );
	}
}