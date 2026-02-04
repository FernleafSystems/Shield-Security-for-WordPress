<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Wordpress\Services\Services;

class VerifySecurityAdminList {

	public function run( array $users ) :array {
		$WPU = Services::WpUsers();

		$filtered = [];
		foreach ( \array_map( '\trim', $users ) as $usernameOrEmail ) {
			$user = null;

			if ( !empty( $usernameOrEmail ) ) {
				if ( Services::Data()->validEmail( $usernameOrEmail ) ) {
					$user = $WPU->getUserByEmail( $usernameOrEmail );
				}
				else {
					$user = $WPU->getUserByUsername( $usernameOrEmail );
					if ( \is_null( $user ) && \is_numeric( $usernameOrEmail ) ) {
						$user = $WPU->getUserById( $usernameOrEmail );
					}
				}
			}

			if ( $user instanceof \WP_User && $user->ID > 0 && $WPU->isUserAdmin( $user ) ) {
				$filtered[] = $user->user_login;
			}
		}

		\natsort( $filtered );
		return \array_unique( $filtered );
	}
}