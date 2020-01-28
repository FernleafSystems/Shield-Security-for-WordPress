<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @param \WP_User       $oUser
	 * @param ShieldUserMeta $oMeta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( $oUser, $oMeta ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();

		$aRoles = array_intersect( $oMod->getSuspendAutoIdleUserRoles(), array_map( 'strtolower', $oUser->roles ) );

		if ( count( $aRoles ) > 0 && $this->isLastVerifiedAtExpired( $oMeta ) ) {
			$oUser = new \WP_Error(
				$this->getCon()->prefix( 'pass-expired' ),
				implode( ' ', [
					__( 'Sorry, this account is suspended because of inactivity.', 'wp-simple-firewall' ),
					__( 'Please reset your password to regain access.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s &rarr;</a>',
						Services::WpGeneral()->getLostPasswordUrl(),
						__( 'Reset', 'wp-simple-firewall' )
					),
				] )
			);
		}
		return $oUser;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	protected function isLastVerifiedAtExpired( $oMeta ) {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();
		return ( Services::Request()->ts() - $oMeta->getLastVerifiedAt() > $oMod->getSuspendAutoIdleTime() );
	}
}