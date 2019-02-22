<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;

class Idle extends Base {

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		if ( $this->isLastVerifiedAtExpired( $oMeta ) ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				'Sorry, this account is suspended due to in-activity. Please reset your password to gain access to your account.'
			);
		}
		return $oUser;
	}
}