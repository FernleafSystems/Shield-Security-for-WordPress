<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password\QueryUserPasswordExpired;
use FernleafSystems\Wordpress\Plugin\Shield\Users\ShieldUserMeta;

class PasswordExpiry extends Base {

	/**
	 * @return \WP_Error|\WP_User
	 */
	protected function processUser( \WP_User $user, ShieldUserMeta $meta ) {
		if ( ( new QueryUserPasswordExpired() )->check( $user ) ) {
			$user = new \WP_Error(
				self::con()->prefix( 'pass-expired' ),
				\implode( ' ', [
					__( 'Sorry, this account is suspended because the password has expired.', 'wp-simple-firewall' ),
					__( 'Please reset your password to regain access.', 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s &rarr;</a>', $this->getResetPasswordURL( 'password_expired' ), __( 'Reset', 'wp-simple-firewall' ) ),
				] )
			);
		}
		return $user;
	}
}