<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;

class Suspended extends Base {

	const HOOK_PRIORITY = 999; // we process hard suspension before all others.

	/**
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, ShieldUserMeta $meta ) {
		if ( $meta->record->hard_suspended_at > 0 ) {
			$user = new \WP_Error(
				$this->getCon()->prefix( 'hard-suspended' ),
				implode( ' ', [
					__( 'Sorry, this account is suspended.', 'wp-simple-firewall' ),
					__( 'Please contact your website administrator.', 'wp-simple-firewall' ),
				] )
			);
		}
		return $user;
	}
}