<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;
use FernleafSystems\Wordpress\Services\Services;

class Idle extends Base {

	/**
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, ShieldUserMeta $meta ) {
		$susCon = self::con()->comps->user_suspend;
		$idleFor = Services::Request()->ts() - $meta->most_recent_activity_at;
		$rolesToSuspend = $susCon->getSuspendAutoIdleUserRoles();

		$isIdle = !empty( \array_intersect( $rolesToSuspend, \array_map( '\strtolower', $user->roles ) ) )
				  &&
				  ( $idleFor > $susCon->getSuspendAutoIdleTime() );

		if ( apply_filters( 'shield/user/is_user_account_idle', $isIdle, $user, $idleFor, $rolesToSuspend ) ) {
			$user = new \WP_Error(
				self::con()->prefix( 'pass-expired' ),
				\implode( ' ', [
					__( 'Sorry, this account is suspended because of inactivity.', 'wp-simple-firewall' ),
					__( 'Please reset your password to regain access.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s &rarr;</a>', $this->getResetPasswordURL( 'idle' ), __( 'Reset', 'wp-simple-firewall' ) ),
				] )
			);
		}
		return $user;
	}
}