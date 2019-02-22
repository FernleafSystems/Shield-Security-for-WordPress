<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;

class Suspended extends Base {

	const HOOK_PRIORITY = 999; // we process hard suspension before all others.

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		if ( $oMeta->is_hard_suspended === true ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'hard-suspended' ),
				'Sorry, this account is suspended. Please contact your website administrator to resolve this.'
			);
		}
		return $oUser;
	}
}