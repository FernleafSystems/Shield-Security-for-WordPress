<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class AdminLoginAlertContextBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array{role_name:string,username:string,user_email:string,ip:string,ip_identity:string}|null
	 */
	public function build( \WP_User $user ) :?array {
		$roleToCheck = $this->roleThreshold();
		$isUserSignificantEnough = false;

		foreach ( $this->userCapToRolesMap() as $role => $cap ) {
			if ( isset( $user->allcaps[ $cap ] ) && $user->allcaps[ $cap ] ) {
				$isUserSignificantEnough = true;
			}
			if ( $roleToCheck === $role ) {
				break;
			}
		}

		return $isUserSignificantEnough ? [
			'role_name'   => \ucwords( \str_replace( '_', ' ', $roleToCheck ) ).'+',
			'username'    => $user->user_login,
			'user_email'  => $user->user_email,
			'ip'          => self::con()->this_req->ip,
			'ip_identity' => $this->ipIdentityName(),
		] : null;
	}

	private function roleThreshold() :string {
		$filtered = apply_filters( self::con()->prefix( 'login-notification-email-role' ), 'administrator' );
		$roleToCheck = \strtolower( \is_string( $filtered ) ? $filtered : 'administrator' );
		return \array_key_exists( $roleToCheck, $this->userCapToRolesMap() ) ? $roleToCheck : 'administrator';
	}

	private function userCapToRolesMap() :array {
		return [
			'network_admin' => 'manage_network',
			'administrator' => 'manage_options',
			'editor'        => 'edit_pages',
			'author'        => 'publish_posts',
			'contributor'   => 'delete_posts',
			'subscriber'    => 'read',
		];
	}

	private function ipIdentityName() :string {
		$ipID = (string)self::con()->this_req->ip_id;

		if ( \in_array( $ipID, [ '', IpID::UNKNOWN, IpID::VISITOR ], true ) ) {
			return '';
		}

		if ( $ipID === IpID::LOOPBACK ) {
			return __( 'Loopback', 'wp-simple-firewall' );
		}

		if ( $ipID === IpID::THIS_SERVER ) {
			return __( 'This Server', 'wp-simple-firewall' );
		}

		$providerName = Services::ServiceProviders()->getProviderName( $ipID );
		return $providerName === 'Unknown' ? '' : $providerName;
	}
}
