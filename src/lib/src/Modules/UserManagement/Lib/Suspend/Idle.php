<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, ShieldUserMeta $meta ) {
		$r = array_intersect( $this->opts()->getSuspendAutoIdleUserRoles(), array_map( 'strtolower', $user->roles ) );

		if ( count( $r ) > 0 && $this->isLastVerifiedAtExpired( $meta ) ) {
			$user = new \WP_Error(
				$this->con()->prefix( 'pass-expired' ),
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

	protected function isLastVerifiedAtExpired( ShieldUserMeta $meta ) :bool {
		return ( Services::Request()->ts() - $meta->last_verified_at > $this->opts()->getSuspendAutoIdleTime() );
	}
}