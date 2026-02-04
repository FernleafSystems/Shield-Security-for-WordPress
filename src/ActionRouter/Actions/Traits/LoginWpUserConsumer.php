<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Trait for actions that operate during the login flow (user NOT authenticated).
 *
 * SECURITY: This trait requires a valid login_nonce to identify the user.
 * The login_nonce is created when the user successfully enters their password
 * and is tied to their user ID. This prevents attackers from targeting
 * arbitrary users during the login flow.
 */
trait LoginWpUserConsumer {

	/**
	 * @throws ActionException
	 */
	public function getLoginWPUser() :\WP_User {
		$userID = (int)$this->action_data[ 'login_wp_user' ] ?? 0;
		$loginNonce = (string)$this->action_data[ 'login_nonce' ] ?? '';

		if ( $userID < 1 || empty( $loginNonce ) ) {
			throw new ActionException( __( 'Invalid login session.', 'wp-simple-firewall' ) );
		}

		$user = Services::WpUsers()->getUserById( $userID );
		if ( !$user instanceof \WP_User ) {
			throw new ActionException( __( 'User not found.', 'wp-simple-firewall' ) );
		}

		// Validate the login_nonce belongs to this user
		if ( !self::con()->comps->mfa->verifyLoginNonce( $user, $loginNonce ) ) {
			throw new ActionException( __( 'Invalid or expired login session.', 'wp-simple-firewall' ) );
		}

		return $user;
	}

	public function hasValidLoginSession() :bool {
		try {
			$this->getLoginWPUser();
			return true;
		}
		catch ( ActionException $e ) {
			return false;
		}
	}
}