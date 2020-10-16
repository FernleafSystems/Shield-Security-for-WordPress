<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @param \WP_User       $user
	 * @param ShieldUserMeta $meta
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, $meta ) {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		$aRoles = array_intersect( $opts->getSuspendAutoIdleUserRoles(), array_map( 'strtolower', $user->roles ) );

		if ( count( $aRoles ) > 0 && $this->isLastVerifiedAtExpired( $meta ) ) {
			$user = new \WP_Error(
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
		return $user;
	}

	/**
	 * @param ShieldUserMeta $oMeta
	 * @return bool
	 */
	protected function isLastVerifiedAtExpired( $oMeta ) {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();
		return ( Services::Request()->ts() - $oMeta->getLastVerifiedAt() > $oOpts->getSuspendAutoIdleTime() );
	}
}